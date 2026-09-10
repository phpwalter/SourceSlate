<?php

declare(strict_types=1);

namespace SourceSlate\Source\Git;

final readonly class GitRef
{
    private function __construct(
        public string $requested,
        public string $revision,
        public string $kind,
    ) {
    }

    public static function branch(string $branch): self
    {
        $branch = trim($branch);
        if ($branch === '') {
            throw new \InvalidArgumentException('Git branch cannot be empty.');
        }

        return new self($branch, 'refs/remotes/origin/' . $branch, 'branch');
    }

    public static function explicit(string $ref): self
    {
        $ref = trim($ref);
        if ($ref === '') {
            throw new \InvalidArgumentException('Git ref cannot be empty.');
        }

        return new self($ref, $ref, 'ref');
    }

    public static function defaultBranch(string $branch): self
    {
        return self::branch($branch);
    }
}
