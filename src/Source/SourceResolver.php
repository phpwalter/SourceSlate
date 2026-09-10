<?php

declare(strict_types=1);

namespace SourceSlate\Source;

use SourceSlate\Source\Git\GitSourceProvider;

final readonly class SourceResolver
{
    public function __construct(private GitSourceProvider $gitSourceProvider)
    {
    }

    public function resolve(SourceRequest $request): SourceWorkspace
    {
        if ($this->looksLikeGitSource($request->source)) {
            return $this->gitSourceProvider->resolve($request);
        }

        $root = realpath($request->source);
        if ($root === false) {
            throw new \RuntimeException(sprintf('Project root does not exist: %s', $request->source));
        }

        return new SourceWorkspace(root: $root, remote: false);
    }

    private function looksLikeGitSource(string $source): bool
    {
        return preg_match('#^(https?|ssh)://#i', $source) === 1
            || preg_match('#^[^@\s]+@[^:\s]+:.+$#', $source) === 1;
    }
}
