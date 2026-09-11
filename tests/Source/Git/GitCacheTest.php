<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Source\Git;

use PHPUnit\Framework\TestCase;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitCacheMetadata;
use SourceSlate\Source\Git\GitRepositoryIdentity;

final class GitCacheTest extends TestCase
{
    public function testRepositoryPathsAreDeterministic(): void
    {
        $root = $this->temporaryDirectory();
        $cache = new GitCache($root);
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/example.git');
        $commit = str_repeat('a', 40);

        try {
            self::assertSame(
                $root . DIRECTORY_SEPARATOR . $identity->cacheKey,
                $cache->repositoryDirectory($identity),
            );
            self::assertSame(
                $cache->repositoryDirectory($identity) . DIRECTORY_SEPARATOR . 'repo.git',
                $cache->bareRepository($identity),
            );
            self::assertSame(
                $cache->repositoryDirectory($identity) . DIRECTORY_SEPARATOR . 'metadata.json',
                $cache->metadataPath($identity),
            );
            self::assertSame(
                $cache->repositoryDirectory($identity) . DIRECTORY_SEPARATOR . 'worktrees' . DIRECTORY_SEPARATOR . $commit,
                $cache->worktreeDirectory($identity, $commit),
            );
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testMissingMetadataProducesFreshMetadata(): void
    {
        $root = $this->temporaryDirectory();
        $cache = new GitCache($root);
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/example.git');

        try {
            $metadata = $cache->loadMetadata($identity);

            self::assertSame($identity->cacheKey, $metadata->identity->cacheKey);
            self::assertNotNull($metadata->createdAt);
            self::assertNotNull($metadata->lastUsedAt);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testMetadataCanBeSavedAndLoaded(): void
    {
        $root = $this->temporaryDirectory();
        $cache = new GitCache($root);
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/example.git');
        $metadata = new GitCacheMetadata(
            identity: $identity,
            lastResolvedRef: 'refs/heads/main',
            lastResolvedCommit: str_repeat('d', 40),
            createdAt: '2026-09-10T10:00:00+00:00',
            lastFetchAt: '2026-09-10T10:01:00+00:00',
            lastUsedAt: '2026-09-10T10:02:00+00:00',
        );

        try {
            $cache->saveMetadata($metadata);
            $loaded = $cache->loadMetadata($identity);

            self::assertSame($metadata->lastResolvedRef, $loaded->lastResolvedRef);
            self::assertSame($metadata->lastResolvedCommit, $loaded->lastResolvedCommit);
            self::assertSame($metadata->createdAt, $loaded->createdAt);
            self::assertSame($metadata->lastFetchAt, $loaded->lastFetchAt);
            self::assertSame($metadata->lastUsedAt, $loaded->lastUsedAt);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testMalformedMetadataJsonIsRejected(): void
    {
        $root = $this->temporaryDirectory();
        $cache = new GitCache($root);
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/example.git');

        try {
            $cache->ensureRepositoryDirectory($identity);
            file_put_contents($cache->metadataPath($identity), '{not-json');

            $this->expectException(\JsonException::class);
            $cache->loadMetadata($identity);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testAlternateWorktreeDirectoryDoesNotReuseCanonicalPath(): void
    {
        $root = $this->temporaryDirectory();
        $cache = new GitCache($root);
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/example.git');
        $commit = str_repeat('e', 40);

        try {
            $canonical = $cache->worktreeDirectory($identity, $commit);
            $first = $cache->alternateWorktreeDirectory($identity, $commit);
            $second = $cache->alternateWorktreeDirectory($identity, $commit);

            self::assertNotSame($canonical, $first);
            self::assertNotSame($first, $second);
            self::assertStringStartsWith($canonical . '-', $first);
            self::assertStringStartsWith($canonical . '-', $second);
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-cache-' . bin2hex(random_bytes(6));
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
