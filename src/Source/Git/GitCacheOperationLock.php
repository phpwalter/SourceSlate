<?php

declare(strict_types=1);

namespace SourceSlate\Source\Git;

final class GitCacheOperationLock
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(
        private readonly string $path,
        private readonly string $subject,
    ) {
    }

    public function acquireExclusive(): void
    {
        $this->acquire(LOCK_EX, 'exclusive');
    }

    public function acquireShared(): void
    {
        $this->acquire(LOCK_SH, 'shared');
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

    private function acquire(int $operation, string $mode): void
    {
        if (is_resource($this->handle)) {
            return;
        }

        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create SourceSlate cache operation lock directory: %s', $directory));
        }

        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open SourceSlate cache operation lock: %s', $this->path));
        }

        if (!flock($handle, $operation)) {
            fclose($handle);
            throw new \RuntimeException(sprintf('Unable to acquire SourceSlate cache operation lock: %s', $this->path));
        }

        if ($operation === LOCK_EX) {
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode([
                'pid' => getmypid(),
                'hostname' => gethostname() ?: null,
                'subject' => $this->subject,
                'mode' => $mode,
                'acquired_at' => gmdate('c'),
            ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
            fflush($handle);
        }

        $this->handle = $handle;
    }
}
