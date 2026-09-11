<?php

declare(strict_types=1);

namespace SourceSlate\Source\Git;

use SourceSlate\Exception\GitException;
use SourceSlate\Exception\SourceSlateException;
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
            throw new SourceSlateException('SS-OUT-0040', '--output is required for remote Git sources.', 40);
        }

        if (!$this->git->isAvailable()) {
            throw new GitException('SS-GIT-0020', 'Git is not available on PATH.', 20);
        }

        $identity = GitRepositoryIdentity::fromUrl($request->source);
        $this->cache->ensureRepositoryDirectory($identity);
        $lock = $this->cache->lock($identity);
        $lock->acquire();

        try {
            $metadata = $this->cache->loadMetadata($identity);
            $bare = $this->cache->bareRepository($identity);
            $cacheStatus = 'miss';
            $beforeCommit = null;
            $gitRef = null;

            if (!is_dir($bare)) {
                if ($request->offline) {
                    throw new GitException('SS-GIT-0021', 'Repository is not available in cache and --offline was requested.', 21);
                }

                $this->git->run(['clone', '--bare', $identity->originalUrl, $bare]);
            } else {
                $gitRef = $this->resolveRequestedRef($request, $bare);
                $beforeCommit = $this->tryResolveCommit($bare, $gitRef);

                if ($request->offline) {
                    $cacheStatus = 'offline';
                } else {
                    try {
                        $this->git->run(['--git-dir=' . $bare, 'fetch', '--prune', '--tags', 'origin']);
                        $cacheStatus = $request->refresh ? 'refreshed' : 'hit';
                    } catch (GitException $exception) {
                        if ($exception->exitCode === 22) {
                            throw $exception;
                        }

                        if ($beforeCommit === null) {
                            throw $exception;
                        }

                        $cacheStatus = 'cached-unverified';
                    }
                }
            }

            $gitRef ??= $this->resolveRequestedRef($request, $bare);
            $commit = $cacheStatus === 'cached-unverified' && $beforeCommit !== null
                ? $beforeCommit
                : $this->git->run(['--git-dir=' . $bare, 'rev-parse', $gitRef->revision . '^{commit}']);

            if (!$request->offline && $cacheStatus !== 'cached-unverified' && $beforeCommit !== null && $beforeCommit !== $commit) {
                $cacheStatus = 'updated';
            }

            $worktree = $this->prepareWorktree($identity, $bare, $commit);

            if ($request->recurseSubmodules) {
                $this->git->run(['submodule', 'update', '--init', '--recursive'], $worktree);
            }

            $root = $this->resolveSourceRoot($worktree, $request->sourcePath);
            $this->cache->saveMetadata($metadata->withResolution($gitRef->requested, $commit, $cacheStatus !== 'offline' && $cacheStatus !== 'cached-unverified'));

            return new SourceWorkspace(
                root: $root,
                remote: true,
                repository: $identity->canonicalUrl,
                requestedRef: $gitRef->requested,
                resolvedCommit: $commit,
                offline: $request->offline || $cacheStatus === 'cached-unverified',
                cacheStatus: $cacheStatus,
            );
        } finally {
            $lock->release();
        }
    }

    private function resolveRequestedRef(SourceRequest $request, string $bare): GitRef
    {
        if ($request->branch !== null) {
            return GitRef::branch($request->branch);
        }

        if ($request->tag !== null) {
            return GitRef::tag($request->tag);
        }

        if ($request->ref !== null) {
            return $this->resolveExplicitRef($request->ref, $bare);
        }

        $symbolic = $this->git->run(['--git-dir=' . $bare, 'symbolic-ref', 'HEAD']);
        $prefix = 'refs/heads/';
        if (!str_starts_with($symbolic, $prefix)) {
            throw new GitException('SS-GIT-0023', sprintf('Unable to resolve repository default branch from %s.', $symbolic), 23);
        }

        return GitRef::defaultBranch(substr($symbolic, strlen($prefix)));
    }

    private function resolveExplicitRef(string $ref, string $bare): GitRef
    {
        $trimmed = trim($ref);
        if ($trimmed === '') {
            throw new GitException('SS-GIT-0023', 'Git ref cannot be empty.', 23);
        }

        if (str_starts_with($trimmed, 'refs/') || preg_match('/^[0-9a-f]{7,40}$/i', $trimmed) === 1) {
            return GitRef::explicit($trimmed);
        }

        $branch = $this->tryResolveRevision($bare, 'refs/heads/' . $trimmed);
        $tag = $this->tryResolveRevision($bare, 'refs/tags/' . $trimmed);

        if ($branch !== null && $tag !== null) {
            throw new GitException(
                'SS-GIT-0108',
                sprintf('Ref "%s" is ambiguous; both refs/heads/%s and refs/tags/%s exist. Use --branch or --tag.', $trimmed, $trimmed, $trimmed),
                23,
            );
        }

        if ($branch !== null) {
            return GitRef::branch($trimmed);
        }

        if ($tag !== null) {
            return GitRef::tag($trimmed);
        }

        return GitRef::explicit($trimmed);
    }

    private function tryResolveCommit(string $bare, GitRef $gitRef): ?string
    {
        return $this->tryResolveRevision($bare, $gitRef->revision . '^{commit}');
    }

    private function tryResolveRevision(string $bare, string $revision): ?string
    {
        try {
            return $this->git->run(['--git-dir=' . $bare, 'rev-parse', '--verify', $revision]);
        } catch (GitException) {
            return null;
        }
    }

    private function prepareWorktree(GitRepositoryIdentity $identity, string $bare, string $commit): string
    {
        $worktree = $this->cache->worktreeDirectory($identity, $commit);

        if (is_dir($worktree)) {
            $status = $this->git->run(['status', '--porcelain'], $worktree);
            if ($status === '') {
                return $worktree;
            }

            $worktree = $this->cache->alternateWorktreeDirectory($identity, $commit);
        }

        $parent = dirname($worktree);
        if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
            throw new GitException('SS-GIT-0025', sprintf('Unable to create worktree directory: %s', $parent), 25);
        }

        $this->git->run(['--git-dir=' . $bare, 'worktree', 'add', '--detach', $worktree, $commit]);

        return $worktree;
    }

    private function resolveSourceRoot(string $worktree, ?string $sourcePath): string
    {
        if ($sourcePath === null || $sourcePath === '') {
            return $worktree;
        }

        $worktreeRoot = realpath($worktree);
        $candidate = realpath($worktree . DIRECTORY_SEPARATOR . $sourcePath);
        if ($worktreeRoot === false || $candidate === false) {
            throw new SourceSlateException('SS-SRC-0012', sprintf('Invalid --source-path: %s', $sourcePath), 12);
        }

        $rootPrefix = rtrim($worktreeRoot, '\\/') . DIRECTORY_SEPARATOR;
        if ($candidate !== $worktreeRoot && !str_starts_with($candidate . DIRECTORY_SEPARATOR, $rootPrefix)) {
            throw new SourceSlateException('SS-SRC-0012', sprintf('Invalid --source-path outside repository: %s', $sourcePath), 12);
        }

        return $candidate;
    }
}
