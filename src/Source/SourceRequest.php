<?php

declare(strict_types=1);

namespace SourceSlate\Source;

final readonly class SourceRequest
{
    public function __construct(
        public string $source,
        public ?string $output = null,
        public ?string $branch = null,
        public ?string $ref = null,
        public ?string $sourcePath = null,
        public bool $refresh = false,
        public bool $offline = false,
        public bool $recurseSubmodules = false,
    ) {
        if ($branch !== null && $ref !== null) {
            throw new \InvalidArgumentException('--branch and --ref cannot be used together.');
        }
    }

    public function requestedRef(): ?string
    {
        return $this->branch !== null ? 'refs/heads/' . $this->branch : $this->ref;
    }
}
