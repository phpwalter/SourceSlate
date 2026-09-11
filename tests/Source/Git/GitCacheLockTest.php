<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Source\Git;

use PHPUnit\Framework\TestCase;
use SourceSlate\Source\Git\GitCacheLock;

final class GitCacheLockTest extends TestCase
{
    public function testAcquireCreatesLockFileWithOwnershipMetadata(): void
    {
        $root = $this->temporaryDirectory();
        $path = $root . DIRECTORY_SEPARATOR . 'locks' . DIRECTORY_SEPARATOR . 'fetch.lock';
        $lock = new GitCacheLock($path);

        try {
            $lock->acquire();

            self::assertFileExists($path);
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(getmypid(), $data['pid']);
            self::assertArrayHasKey('hostname', $data);
            self::assertArrayHasKey('acquired_at', $data);
        } finally {
            $lock->release();
            $this->removeDirectory($root);
        }
    }

    public function testReleaseAllowsAnotherProcessHandleToAcquireTheSameLock(): void
    {
        $root = $this->temporaryDirectory();
        $path = $root . DIRECTORY_SEPARATOR . 'locks' . DIRECTORY_SEPARATOR . 'fetch.lock';
        $first = new GitCacheLock($path);

        try {
            $first->acquire();
            $first->release();

            $handle = fopen($path, 'c+');
            self::assertIsResource($handle);
            self::assertTrue(flock($handle, LOCK_EX | LOCK_NB));
            flock($handle, LOCK_UN);
            fclose($handle);
        } finally {
            $first->release();
            $this->removeDirectory($root);
        }
    }

    public function testReleaseIsIdempotent(): void
    {
        $root = $this->temporaryDirectory();
        $path = $root . DIRECTORY_SEPARATOR . 'locks' . DIRECTORY_SEPARATOR . 'fetch.lock';
        $lock = new GitCacheLock($path);

        try {
            $lock->acquire();
            $lock->release();
            $lock->release();

            self::assertFileExists($path);
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-lock-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);

        return $root;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child) && !is_link($child)) {
                $this->removeDirectory($child);
            } else {
                @unlink($child);
            }
        }

        @rmdir($path);
    }
}
