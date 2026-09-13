<?php

declare(strict_types=1);

namespace SourceSlate\Build;

final class BuildStagingArea
{
    private string $path;

    public function __construct(private readonly string $destination)
    {
        $parent = dirname($destination);
        if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
            throw new \RuntimeException(sprintf('Unable to create output parent directory: %s', $parent));
        }

        $lock = new BuildPublicationLock($this->destination);
        $lock->acquire();
        try {
            $this->recoverInterruptedPublication();
            $this->cleanupStaleStagingDirectories($parent);
        } finally {
            $lock->release();
        }

        $this->path = $parent . DIRECTORY_SEPARATOR . '.sourceslate-build-' . bin2hex(random_bytes(8));
        if (!mkdir($this->path, 0777, true) && !is_dir($this->path)) {
            throw new \RuntimeException(sprintf('Unable to create SourceSlate staging directory: %s', $this->path));
        }
        file_put_contents($this->path . DIRECTORY_SEPARATOR . '.sourceslate-staging-owner.json', json_encode([
            'pid' => getmypid(),
            'hostname' => gethostname() ?: null,
            'destination' => $this->destination,
            'created_at' => gmdate('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function publish(): void
    {
        $lock = new BuildPublicationLock($this->destination);
        $lock->acquire();
        try {
            $this->recoverInterruptedPublication();

            $backup = null;
            if (file_exists($this->destination)) {
                $backup = $this->destination . '.sourceslate-previous-' . bin2hex(random_bytes(6));
                if (!rename($this->destination, $backup)) {
                    throw new \RuntimeException(sprintf('Unable to preserve existing SourceSlate output: %s', $this->destination));
                }
            }

            if (!rename($this->path, $this->destination)) {
                if ($backup !== null && !file_exists($this->destination)) {
                    @rename($backup, $this->destination);
                }
                throw new \RuntimeException(sprintf('Unable to publish SourceSlate output: %s', $this->destination));
            }

            @unlink($this->destination . DIRECTORY_SEPARATOR . '.sourceslate-staging-owner.json');

            if ($backup !== null) {
                $this->removeTree($backup);
            }
        } finally {
            $lock->release();
        }
    }

    public function discard(): void
    {
        if (is_dir($this->path)) {
            $this->removeTree($this->path);
        }
    }

    private function recoverInterruptedPublication(): void
    {
        $backups = glob($this->destination . '.sourceslate-previous-*') ?: [];
        if ($backups === []) {
            return;
        }

        usort($backups, static function (string $a, string $b): int {
            $aTime = filemtime($a) ?: 0;
            $bTime = filemtime($b) ?: 0;
            return $bTime <=> $aTime;
        });

        if (!file_exists($this->destination)) {
            $restore = array_shift($backups);
            if ($restore !== null && !rename($restore, $this->destination)) {
                throw new \RuntimeException(sprintf('Unable to restore interrupted SourceSlate output publication from %s.', $restore));
            }
        }

        foreach ($backups as $backup) {
            if (file_exists($backup)) {
                $this->removeTree($backup);
            }
        }

        if (file_exists($this->destination)) {
            foreach (glob($this->destination . '.sourceslate-previous-*') ?: [] as $backup) {
                $this->removeTree($backup);
            }
        }
    }

    private function cleanupStaleStagingDirectories(string $parent): void
    {
        foreach (glob($parent . DIRECTORY_SEPARATOR . '.sourceslate-build-*') ?: [] as $candidate) {
            if (!is_dir($candidate)) {
                continue;
            }

            $owner = $candidate . DIRECTORY_SEPARATOR . '.sourceslate-staging-owner.json';
            if (!is_file($owner)) {
                $this->removeTree($candidate);
                continue;
            }

            $data = json_decode((string) file_get_contents($owner), true);
            if (!is_array($data)) {
                $this->removeTree($candidate);
                continue;
            }

            $created = isset($data['created_at']) ? strtotime((string) $data['created_at']) : false;
            if ($created === false || $created < time() - 86400) {
                $this->removeTree($candidate);
            }
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }
}
