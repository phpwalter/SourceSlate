<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Source\Git;

use PHPUnit\Framework\TestCase;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitRepositoryIdentity;

final class GitCacheOperationLockTest extends TestCase
{
    public function testOperationLocksLiveOutsideReplaceableCacheRoot(): void
    {
        $root = $this->temporaryDirectory() . DIRECTORY_SEPARATOR . 'repositories';
        $cache = new GitCache($root);
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/example.git');

        try {
            self::assertSame($root . '.locks', $cache->operationLockRoot());
            self::assertStringStartsNotWith($root . DIRECTORY_SEPARATOR, $cache->operationLockRoot());

            $maintenance = $cache->maintenanceLock();
            $maintenance->acquireExclusive();
            $maintenance->release();

            self::assertFileExists($cache->operationLockRoot() . DIRECTORY_SEPARATOR . 'cache-maintenance.lock');

            $operation = $cache->operationLock($identity);
            $operation->acquireExclusive();
            $operation->release();

            $path = $cache->operationLockRoot()
                . DIRECTORY_SEPARATOR . 'repositories'
                . DIRECTORY_SEPARATOR . $identity->cacheKey . '.lock';
            self::assertFileExists($path);

            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($identity->canonicalUrl, $data['subject']);
            self::assertSame('exclusive', $data['mode']);
            self::assertArrayHasKey('pid', $data);
            self::assertArrayHasKey('acquired_at', $data);
        } finally {
            $this->removeDirectory(dirname($root));
            $this->removeDirectory($root . '.locks');
        }
    }

    public function testSharedMaintenanceLockCanBeAcquiredAndReleased(): void
    {
        $base = $this->temporaryDirectory();
        $cache = new GitCache($base . DIRECTORY_SEPARATOR . 'repositories');

        try {
            $lock = $cache->maintenanceLock();
            $lock->acquireShared();
            $lock->release();

            self::assertFileExists($cache->operationLockRoot() . DIRECTORY_SEPARATOR . 'cache-maintenance.lock');
        } finally {
            $this->removeDirectory($base);
            $this->removeDirectory($base . DIRECTORY_SEPARATOR . 'repositories.locks');
        }
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-operation-lock-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        return $root;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
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
