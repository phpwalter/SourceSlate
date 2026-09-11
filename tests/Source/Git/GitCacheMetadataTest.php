<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Source\Git;

use PHPUnit\Framework\TestCase;
use SourceSlate\Source\Git\GitCacheMetadata;
use SourceSlate\Source\Git\GitRepositoryIdentity;

final class GitCacheMetadataTest extends TestCase
{
    public function testCreateInitializesSchemaAndUsageTimestamps(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/example.git');
        $metadata = GitCacheMetadata::create($identity);
        $data = $metadata->toArray();

        self::assertSame(GitCacheMetadata::SCHEMA_VERSION, $data['schema_version']);
        self::assertSame($identity->canonicalUrl, $data['repository']['canonical_url']);
        self::assertSame($identity->cacheKey, $data['repository']['cache_key']);
        self::assertNotNull($metadata->createdAt);
        self::assertNotNull($metadata->lastUsedAt);
        self::assertNull($metadata->lastFetchAt);
        self::assertNull($metadata->lastResolvedRef);
        self::assertNull($metadata->lastResolvedCommit);
    }

    public function testRoundTripPreservesPersistedState(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('git@github.com:acme/example.git');
        $original = new GitCacheMetadata(
            identity: $identity,
            lastResolvedRef: 'refs/heads/main',
            lastResolvedCommit: str_repeat('a', 40),
            createdAt: '2026-09-10T10:00:00+00:00',
            lastFetchAt: '2026-09-10T10:01:00+00:00',
            lastUsedAt: '2026-09-10T10:02:00+00:00',
        );

        $restored = GitCacheMetadata::fromArray($identity, $original->toArray());

        self::assertSame($original->lastResolvedRef, $restored->lastResolvedRef);
        self::assertSame($original->lastResolvedCommit, $restored->lastResolvedCommit);
        self::assertSame($original->createdAt, $restored->createdAt);
        self::assertSame($original->lastFetchAt, $restored->lastFetchAt);
        self::assertSame($original->lastUsedAt, $restored->lastUsedAt);
    }

    public function testResolutionUpdatesReferenceCommitAndUsageWithoutFetchTimestampWhenCacheWasUsed(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/example.git');
        $metadata = new GitCacheMetadata(
            identity: $identity,
            createdAt: '2026-09-10T10:00:00+00:00',
            lastFetchAt: '2026-09-10T10:01:00+00:00',
            lastUsedAt: '2026-09-10T10:02:00+00:00',
        );

        $updated = $metadata->withResolution('refs/heads/main', str_repeat('b', 40), false);

        self::assertSame('refs/heads/main', $updated->lastResolvedRef);
        self::assertSame(str_repeat('b', 40), $updated->lastResolvedCommit);
        self::assertSame($metadata->createdAt, $updated->createdAt);
        self::assertSame($metadata->lastFetchAt, $updated->lastFetchAt);
        self::assertNotSame($metadata->lastUsedAt, $updated->lastUsedAt);
    }

    public function testResolutionRecordsFetchTimestampWhenRemoteWasFetched(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/example.git');
        $metadata = new GitCacheMetadata(identity: $identity, createdAt: '2026-09-10T10:00:00+00:00');

        $updated = $metadata->withResolution('refs/tags/v1.0.0', str_repeat('c', 40), true);

        self::assertNotNull($updated->lastFetchAt);
        self::assertNotNull($updated->lastUsedAt);
    }

    public function testUnsupportedSchemaVersionIsRejected(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/example.git');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported SourceSlate Git cache metadata schema: 999');

        GitCacheMetadata::fromArray($identity, ['schema_version' => 999]);
    }
}
