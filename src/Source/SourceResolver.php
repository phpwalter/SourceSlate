<?php

declare(strict_types=1);

namespace SourceSlate\Source;

use SourceSlate\Source\Git\GitClient;
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

        $git = (new LocalGitInspector(new GitClient()))->inspect($root);

        return new SourceWorkspace(
            root: $root,
            remote: false,
            repository: isset($git['repository']) && is_string($git['repository']) ? $git['repository'] : null,
            resolvedCommit: isset($git['commit']) && is_string($git['commit']) ? $git['commit'] : null,
            gitState: isset($git['state']) && is_string($git['state']) ? $git['state'] : null,
            branch: isset($git['branch']) && is_string($git['branch']) ? $git['branch'] : null,
        );
    }

    private function looksLikeGitSource(string $source): bool
    {
        return preg_match('#^(https?|ssh)://#i', $source) === 1
            || preg_match('#^[^@\s]+@[^:\s]+:.+$#', $source) === 1;
    }
}
