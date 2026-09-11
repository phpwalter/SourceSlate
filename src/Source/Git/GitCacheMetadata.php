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

        return new self(
            identity: $identity,
            createdAt: $now,
            lastUsedAt: $now,
        );
    }

    public static function fromArray(GitRepositoryIdentity $identity, array $data): self
    {
        $schemaVersion = $data['schema_version'] ?? null;
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new \RuntimeException(sprintf(
                'Unsupported SourceSlate Git cache metadata schema: %s',
                is_scalar($schemaVersion) ? (string) $schemaVersion : 'unknown',
            ));
        }

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
