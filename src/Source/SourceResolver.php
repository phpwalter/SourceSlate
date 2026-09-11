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
        if ($request->sourceType === 'git') {
            return $this->gitSourceProvider->resolve($request);
        }

        if ($request->sourceType === 'local') {
            return $this->resolveLocal($request->source);
        }

        if ($this->looksLikeGitSource($request->source)) {
            return $this->gitSourceProvider->resolve($request);
        }

        return $this->resolveLocal($request->source);
    }

    private function resolveLocal(string $source): SourceWorkspace
    {
        $root = realpath($source);
        if ($root === false) {
            throw new \RuntimeException(sprintf('Project root does not exist: %s', $source));
        }

        $git = (new LocalGitInspector(new \SourceSlate\Source\Git\GitClient()))->inspect($root);

        return new SourceWorkspace(
            root: $root,
            remote: false,
            repository: isset($git['repository']) ? (string) $git['repository'] : null,
            requestedRef: isset($git['branch']) ? (string) $git['branch'] : null,
            resolvedCommit: isset($git['commit']) ? (string) $git['commit'] : null,
            gitState: isset($git['state']) ? (string) $git['state'] : null,
        );
    }

    private function looksLikeGitSource(string $source): bool
    {
        return preg_match('#^(https?|ssh)://#i', $source) === 1
            || preg_match('#^[^@\s]+@[^:\s]+:.+$#', $source) === 1;
    }
}
