<?php

declare(strict_types=1);

namespace SourceSlate\Build;

final class BuildPublicationLock
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(private readonly string $destination)
    {
    }

    public function acquire(): void
    {
        $parent = dirname($this->destination);
        if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
            throw new \RuntimeException(sprintf('Unable to create SourceSlate publication lock directory: %s', $parent));
        }

        $path = $parent . DIRECTORY_SEPARATOR . '.sourceslate-publish-' . hash('sha256', $this->destination) . '.lock';
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open SourceSlate publication lock: %s', $path));
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new \RuntimeException(sprintf('Unable to acquire SourceSlate publication lock: %s', $path));
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode([
            'pid' => getmypid(),
            'hostname' => gethostname() ?: null,
            'destination' => $this->destination,
            'acquired_at' => gmdate('c'),
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
        fflush($handle);

        $this->handle = $handle;
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
