<?php

declare(strict_types=1);

namespace SourceSlate\Source;

final readonly class SourceWorkspace
{
    public function __construct(
        public string $root,
        public bool $remote,
        public ?string $repository = null,
        public ?string $requestedRef = null,
        public ?string $resolvedCommit = null,
        public bool $offline = false,
        public ?string $cacheStatus = null,
        public ?string $gitState = null,
        public ?string $branch = null,
        public ?string $repositoryRoot = null,
    ) {
    }
}
