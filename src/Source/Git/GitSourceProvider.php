<?php

declare(strict_types=1);

namespace SourceSlate\Source\Git;

use SourceSlate\Source\SourceRequest;
use SourceSlate\Source\SourceWorkspace;

final readonly class GitSourceProvider
{
    public function __construct(
        private GitClient $git,
        private GitCache $cache,
    ) {
    }

    public function resolve(SourceRequest $request): SourceWorkspace
    {
        if ($request->output === null || trim($request->output) === '') {
            throw new \InvalidArgumentException('--output is required for remote Git sources.');
        }

        if (!$this->git->isAvailable()) {
            throw new \RuntimeException('Git is not available on PATH.');
        }

        $identity = GitRepositoryIdentity::fromUrl($request->source);
        $this->cache->ensureRepositoryDirectory($identity);
        $metadata = $this->cache->loadMetadata($identity);
        $bare = $this->cache->bareRepository($identity);
        $fetched = false;

        if (!is_dir($bare)) {
            if ($request->offline) {
                throw new \RuntimeException('Repository is not available in cache and --offline was requested.');
            }

            $this->git->run(['clone', '--bare', $identity->originalUrl, $bare]);
            $fetched = true;
        } elseif (!$request->offline) {
            $this->git->run(['--git-dir=' . $bare, 'fetch', '--prune', '--tags', 'origin']);
            $fetched = true;
        }

        $gitRef = $this->resolveRequestedRef($request, $bare);
        $commit = $this->git->run(['--git-dir=' . $bare, 'rev-parse', $gitRef->revision . '^{commit}']);
        $worktree = $this->cache->worktreeDirectory($identity, $commit);

        if (!is_dir($worktree)) {
            $parent = dirname($worktree);
            if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
                throw new \RuntimeException(sprintf('Unable to create worktree directory: %s', $parent));
            }

            $this->git->run(['--git-dir=' . $bare, 'worktree', 'add', '--detach', $worktree, $commit]);
        }

        $root = $this->resolveSourceRoot($worktree, $request->sourcePath);
        $this->cache->saveMetadata($metadata->withResolution($gitRef->requested, $commit, $fetched));

        return new SourceWorkspace(
            root: $root,
            remote: true,
            repository: $identity->canonicalUrl,
            requestedRef: $gitRef->requested,
            resolvedCommit: $commit,
            offline: $request->offline,
        );
    }

    private function resolveRequestedRef(SourceRequest $request, string $bare): GitRef
    {
        if ($request->branch !== null) {
            return GitRef::branch($request->branch);
        }

        if ($request->ref !== null) {
            return GitRef::explicit($request->ref);
        }

        $symbolic = $this->git->run(['--git-dir=' . $bare, 'symbolic-ref', 'HEAD']);
        $prefix = 'refs/heads/';
        if (!str_starts_with($symbolic, $prefix)) {
            throw new \RuntimeException(sprintf('Unable to resolve repository default branch from %s.', $symbolic));
        }

        return GitRef::defaultBranch(substr($symbolic, strlen($prefix)));
    }

    private function resolveSourceRoot(string $worktree, ?string $sourcePath): string
    {
        if ($sourcePath === null || $sourcePath === '') {
            return $worktree;
        }

        $worktreeRoot = realpath($worktree);
        $candidate = realpath($worktree . DIRECTORY_SEPARATOR . $sourcePath);
        if ($worktreeRoot === false || $candidate === false) {
            throw new \RuntimeException(sprintf('Invalid --source-path: %s', $sourcePath));
        }

        $rootPrefix = rtrim($worktreeRoot, '\\/') . DIRECTORY_SEPARATOR;
        if ($candidate !== $worktreeRoot && !str_starts_with($candidate . DIRECTORY_SEPARATOR, $rootPrefix)) {
            throw new \RuntimeException(sprintf('Invalid --source-path outside repository: %s', $sourcePath));
        }

        return $candidate;
    }
}
