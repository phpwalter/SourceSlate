<?php

declare(strict_types=1);

namespace SourceSlate\Source\Git;

final class GitCacheLock
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(private readonly string $path)
    {
    }

    public function acquire(): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create SourceSlate cache lock directory: %s', $directory));
        }

        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open SourceSlate cache lock: %s', $this->path));
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new \RuntimeException(sprintf('Unable to acquire SourceSlate cache lock: %s', $this->path));
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode([
            'pid' => getmypid(),
            'hostname' => gethostname() ?: null,
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
