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

        return new self($branch, 'refs/heads/' . $branch, 'branch');
    }

    public static function tag(string $tag): self
    {
        $tag = trim($tag);
        if ($tag === '') {
            throw new \InvalidArgumentException('Git tag cannot be empty.');
        }

        return new self($tag, 'refs/tags/' . $tag, 'tag');
    }

    public static function explicit(string $ref): self
    {
        $ref = trim($ref);
        if ($ref === '') {
            throw new \InvalidArgumentException('Git ref cannot be empty.');
        }

        return new self($ref, $ref, 'ref');
    }

    public static function resolved(string $requested, string $revision, string $kind): self
    {
        return new self($requested, $revision, $kind);
    }

    public static function defaultBranch(string $branch): self
    {
        return self::branch($branch);
    }
}
