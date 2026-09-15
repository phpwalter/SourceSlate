<?php

declare(strict_types=1);

namespace SourceSlate\Source\Git;

final readonly class GitCacheMetadata
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public GitRepositoryIdentity $identity,
        public ?string $lastResolvedRef = null,
        public ?string $lastResolvedCommit = null,
        public ?string $createdAt = null,
        public ?string $lastFetchAt = null,
        public ?string $lastUsedAt = null,
    ) {
    }

    public static function create(GitRepositoryIdentity $identity): self
    {
        $now = gmdate('c');
        return new self(identity: $identity, createdAt: $now, lastUsedAt: $now);
    }

    public static function fromArray(GitRepositoryIdentity $identity, array $data): self
    {
        $data = self::migrateArray($data);
        $state = is_array($data['state'] ?? null) ? $data['state'] : [];

        return new self(
            identity: $identity,
            lastResolvedRef: isset($state['last_resolved_ref']) ? (string) $state['last_resolved_ref'] : null,
            lastResolvedCommit: isset($state['last_resolved_commit']) ? (string) $state['last_resolved_commit'] : null,
            createdAt: isset($state['created_at']) ? (string) $state['created_at'] : null,
            lastFetchAt: isset($state['last_fetch_at']) ? (string) $state['last_fetch_at'] : null,
            lastUsedAt: isset($state['last_used_at']) ? (string) $state['last_used_at'] : null,
        );
    }

    public static function schemaVersion(array $data): int
    {
        $version = $data['schema_version'] ?? 0;
        return is_int($version) ? $version : (is_numeric($version) ? (int) $version : -1);
    }

    public static function isLegacy(array $data): bool
    {
        return self::schemaVersion($data) === 0;
    }

    public static function migrateArray(array $data): array
    {
        $schemaVersion = self::schemaVersion($data);
        if ($schemaVersion === self::SCHEMA_VERSION) {
            return $data;
        }

        if ($schemaVersion === 0) {
            if (!is_array($data['repository'] ?? null) || !is_array($data['state'] ?? null)) {
                throw new \RuntimeException('Legacy SourceSlate Git cache metadata is missing repository or state data.');
            }
            $data['schema_version'] = self::SCHEMA_VERSION;
            return $data;
        }

        throw new \RuntimeException(sprintf('Unsupported SourceSlate Git cache metadata schema: %s', $schemaVersion >= 0 ? (string) $schemaVersion : 'unknown'));
    }

    public function withResolution(string $ref, string $commit, bool $fetched): self
    {
        $now = gmdate('c');
        return new self(
            identity: $this->identity,
            lastResolvedRef: $ref,
            lastResolvedCommit: $commit,
            createdAt: $this->createdAt ?? $now,
            lastFetchAt: $fetched ? $now : $this->lastFetchAt,
            lastUsedAt: $now,
        );
    }

    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'repository' => [
                'canonical_url' => $this->identity->canonicalUrl,
                'transport_url' => $this->identity->redactedUrl,
                'cache_key' => $this->identity->cacheKey,
            ],
            'state' => [
                'created_at' => $this->createdAt,
                'last_fetch_at' => $this->lastFetchAt,
                'last_used_at' => $this->lastUsedAt,
                'last_resolved_ref' => $this->lastResolvedRef,
                'last_resolved_commit' => $this->lastResolvedCommit,
            ],
        ];
    }
}
