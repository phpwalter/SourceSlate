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

        $this->recoverInterruptedPublication();

        $this->path = $parent . DIRECTORY_SEPARATOR . '.sourceslate-build-' . bin2hex(random_bytes(8));
        if (!mkdir($this->path, 0777, true) && !is_dir($this->path)) {
            throw new \RuntimeException(sprintf('Unable to create SourceSlate staging directory: %s', $this->path));
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    public function publish(): void
    {
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

        if ($backup !== null) {
            $this->removeTree($backup);
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
                throw new \RuntimeException(sprintf(
                    'Unable to restore interrupted SourceSlate output publication from %s.',
                    $restore,
                ));
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
