<?php

declare(strict_types=1);

namespace SourceSlate\Source\Git;

final readonly class GitCache
{
    public function __construct(private string $root)
    {
    }

    public static function default(): self
    {
        $base = getenv('SOURCESLATE_CACHE_DIR');
        if (is_string($base) && $base !== '') {
            return new self($base);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $localAppData = getenv('LOCALAPPDATA');
            $base = is_string($localAppData) && $localAppData !== ''
                ? $localAppData . DIRECTORY_SEPARATOR . 'SourceSlate' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'repositories'
                : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'SourceSlate' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'repositories';
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $home = getenv('HOME') ?: sys_get_temp_dir();
            $base = $home . DIRECTORY_SEPARATOR . 'Library' . DIRECTORY_SEPARATOR . 'Caches' . DIRECTORY_SEPARATOR . 'SourceSlate' . DIRECTORY_SEPARATOR . 'repositories';
        } else {
            $home = getenv('HOME') ?: sys_get_temp_dir();
            $base = $home . DIRECTORY_SEPARATOR . '.cache' . DIRECTORY_SEPARATOR . 'sourceslate' . DIRECTORY_SEPARATOR . 'repositories';
        }

        return new self($base);
    }

    public function repositoryDirectory(GitRepositoryIdentity $identity): string
    {
        return $this->root . DIRECTORY_SEPARATOR . $identity->cacheKey;
    }

    public function bareRepository(GitRepositoryIdentity $identity): string
    {
        return $this->repositoryDirectory($identity) . DIRECTORY_SEPARATOR . 'repo.git';
    }

    public function worktreeDirectory(GitRepositoryIdentity $identity, string $commit): string
    {
        return $this->repositoryDirectory($identity) . DIRECTORY_SEPARATOR . 'worktrees' . DIRECTORY_SEPARATOR . $commit;
    }

    public function ensureRepositoryDirectory(GitRepositoryIdentity $identity): void
    {
        $path = $this->repositoryDirectory($identity);
        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Unable to create SourceSlate cache directory: %s', $path));
        }
    }
}
