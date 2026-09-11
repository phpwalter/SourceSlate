<?php

declare(strict_types=1);

namespace SourceSlate\Source;

final readonly class SourceRequest
{
    public function __construct(
        public string $source,
        public ?string $output = null,
        public ?string $branch = null,
        public ?string $tag = null,
        public ?string $ref = null,
        public ?string $sourcePath = null,
        public bool $refresh = false,
        public bool $offline = false,
        public bool $recurseSubmodules = false,
        public ?string $sourceType = null,
        public int $gitTimeout = 60,
    ) {
        $selectors = array_filter([$branch, $tag, $ref], static fn (?string $value): bool => $value !== null);
        if (count($selectors) > 1) {
            throw new \InvalidArgumentException('--branch, --tag, and --ref are mutually exclusive.');
        }

        if ($sourceType !== null && !in_array($sourceType, ['local', 'git'], true)) {
            throw new \InvalidArgumentException('--source-type must be either local or git.');
        }

        if ($gitTimeout < 1) {
            throw new \InvalidArgumentException('--git-timeout must be greater than zero.');
        }
    }

    public function requestedRef(): ?string
    {
        if ($this->branch !== null) {
            return 'refs/heads/' . $this->branch;
        }

        if ($this->tag !== null) {
            return 'refs/tags/' . $this->tag;
        }

        return $this->ref;
    }
}
