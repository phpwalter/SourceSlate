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

    public function root(): string
    {
        return $this->root;
    }

    public function repositoryDirectory(GitRepositoryIdentity $identity): string
    {
        return $this->root . DIRECTORY_SEPARATOR . $identity->cacheKey;
    }

    public function bareRepository(GitRepositoryIdentity $identity): string
    {
        return $this->repositoryDirectory($identity) . DIRECTORY_SEPARATOR . 'repo.git';
    }

    public function metadataPath(GitRepositoryIdentity $identity): string
    {
        return $this->repositoryDirectory($identity) . DIRECTORY_SEPARATOR . 'metadata.json';
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

    public function loadMetadata(GitRepositoryIdentity $identity): GitCacheMetadata
    {
        $path = $this->metadataPath($identity);
        if (!is_file($path)) {
            return GitCacheMetadata::create($identity);
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException(sprintf('Unable to read SourceSlate Git cache metadata: %s', $path));
        }

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException(sprintf('Invalid SourceSlate Git cache metadata: %s', $path));
        }

        return GitCacheMetadata::fromArray($identity, $data);
    }

    public function saveMetadata(GitCacheMetadata $metadata): void
    {
        $this->ensureRepositoryDirectory($metadata->identity);
        $path = $this->metadataPath($metadata->identity);
        $json = json_encode($metadata->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Unable to write SourceSlate Git cache metadata: %s', $path));
        }
    }
}
