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
        $bare = $this->cache->bareRepository($identity);

        if (!is_dir($bare)) {
            if ($request->offline) {
                throw new \RuntimeException('Repository is not available in cache and --offline was requested.');
            }
            $this->git->run(['clone', '--bare', $identity->originalUrl, $bare]);
        } elseif (!$request->offline) {
            $this->git->run(['--git-dir=' . $bare, 'fetch', '--prune', 'origin']);
        }

        $requestedRef = $request->requestedRef();
        $ref = $requestedRef ?? 'HEAD';
        $commit = $this->git->run(['--git-dir=' . $bare, 'rev-parse', $ref . '^{commit}']);
        $worktree = $this->cache->worktreeDirectory($identity, $commit);

        if (!is_dir($worktree)) {
            $parent = dirname($worktree);
            if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
                throw new \RuntimeException(sprintf('Unable to create worktree directory: %s', $parent));
            }
            $this->git->run(['--git-dir=' . $bare, 'worktree', 'add', '--detach', $worktree, $commit]);
        }

        $root = $worktree;
        if ($request->sourcePath !== null && $request->sourcePath !== '') {
            $candidate = realpath($worktree . DIRECTORY_SEPARATOR . $request->sourcePath);
            if ($candidate === false || !str_starts_with($candidate, realpath($worktree) ?: $worktree)) {
                throw new \RuntimeException(sprintf('Invalid --source-path: %s', $request->sourcePath));
            }
            $root = $candidate;
        }

        return new SourceWorkspace(
            root: $root,
            remote: true,
            repository: $identity->canonicalUrl,
            requestedRef: $requestedRef,
            resolvedCommit: $commit,
            offline: $request->offline,
        );
    }
}
