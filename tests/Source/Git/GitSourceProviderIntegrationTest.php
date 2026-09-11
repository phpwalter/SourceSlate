<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Source\Git;

use PHPUnit\Framework\TestCase;
use SourceSlate\Exception\GitException;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitClient;
use SourceSlate\Source\Git\GitSourceProvider;
use SourceSlate\Source\SourceRequest;

final class GitSourceProviderIntegrationTest extends TestCase
{
    public function testRemoteRepositoryCanBeResolvedThenReusedOffline(): void
    {
        $root = $this->temporaryDirectory();
        $remote = $this->createRemoteRepository($root);
        $cache = new GitCache($root . DIRECTORY_SEPARATOR . 'cache');
        $provider = new GitSourceProvider(new GitClient(30), $cache);
        $source = $this->fileUrl($remote);

        try {
            $online = $provider->resolve(new SourceRequest(
                source: $source,
                output: $root . DIRECTORY_SEPARATOR . 'docs-online',
                branch: 'main',
            ));

            self::assertTrue($online->remote);
            self::assertSame('miss', $online->cacheStatus);
            self::assertFalse($online->offline);
            self::assertFileExists($online->root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php');
            self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', (string) $online->resolvedCommit);

            $offline = $provider->resolve(new SourceRequest(
                source: $source,
                output: $root . DIRECTORY_SEPARATOR . 'docs-offline',
                branch: 'main',
                offline: true,
            ));

            self::assertTrue($offline->remote);
            self::assertSame('offline', $offline->cacheStatus);
            self::assertTrue($offline->offline);
            self::assertSame($online->resolvedCommit, $offline->resolvedCommit);
            self::assertSame($online->root, $offline->root);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testOfflineCacheMissFailsDeterministically(): void
    {
        $root = $this->temporaryDirectory();
        $cache = new GitCache($root . DIRECTORY_SEPARATOR . 'cache');
        $provider = new GitSourceProvider(new GitClient(30), $cache);

        try {
            $this->expectException(GitException::class);
            $this->expectExceptionMessage('Repository is not available in cache and --offline was requested.');

            $provider->resolve(new SourceRequest(
                source: $this->fileUrl($root . DIRECTORY_SEPARATOR . 'missing.git'),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
                branch: 'main',
                offline: true,
            ));
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testFetchUpdatesCachedBranchToNewCommit(): void
    {
        $root = $this->temporaryDirectory();
        $remote = $this->createRemoteRepository($root);
        $cache = new GitCache($root . DIRECTORY_SEPARATOR . 'cache');
        $git = new GitClient(30);
        $provider = new GitSourceProvider($git, $cache);
        $source = $this->fileUrl($remote);

        try {
            $initial = $provider->resolve(new SourceRequest(
                source: $source,
                output: $root . DIRECTORY_SEPARATOR . 'docs-initial',
                branch: 'main',
            ));

            $work = $root . DIRECTORY_SEPARATOR . 'work';
            file_put_contents($work . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php', "<?php\n\nfinal class Example { public const VERSION = 2; }\n");
            $git->run(['add', '.'], $work);
            $git->run(['-c', 'user.name=SourceSlate Tests', '-c', 'user.email=sourceslate@example.test', 'commit', '-m', 'second'], $work);
            $git->run(['push', 'origin', 'main'], $work);

            $updated = $provider->resolve(new SourceRequest(
                source: $source,
                output: $root . DIRECTORY_SEPARATOR . 'docs-updated',
                branch: 'main',
                refresh: true,
            ));

            self::assertNotSame($initial->resolvedCommit, $updated->resolvedCommit);
            self::assertSame('updated', $updated->cacheStatus);
            self::assertStringContainsString(
                'VERSION = 2',
                (string) file_get_contents($updated->root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php'),
            );
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testSourcePathIsRestrictedToRepositoryWorktree(): void
    {
        $root = $this->temporaryDirectory();
        $remote = $this->createRemoteRepository($root);
        $provider = new GitSourceProvider(new GitClient(30), new GitCache($root . DIRECTORY_SEPARATOR . 'cache'));

        try {
            $this->expectException(\SourceSlate\Exception\SourceSlateException::class);
            $this->expectExceptionMessage('Invalid --source-path');

            $provider->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
                branch: 'main',
                sourcePath: '..',
            ));
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function createRemoteRepository(string $root): string
    {
        $git = new GitClient(30);
        $work = $root . DIRECTORY_SEPARATOR . 'work';
        $remote = $root . DIRECTORY_SEPARATOR . 'remote.git';

        mkdir($work . DIRECTORY_SEPARATOR . 'src', 0777, true);
        file_put_contents($work . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php', "<?php\n\nfinal class Example { public const VERSION = 1; }\n");

        $git->run(['init', '-b', 'main'], $work);
        $git->run(['add', '.'], $work);
        $git->run(['-c', 'user.name=SourceSlate Tests', '-c', 'user.email=sourceslate@example.test', 'commit', '-m', 'initial'], $work);
        $git->run(['init', '--bare', $remote]);
        $git->run(['remote', 'add', 'origin', $this->fileUrl($remote)], $work);
        $git->run(['push', '-u', 'origin', 'main'], $work);
        $git->run(['symbolic-ref', 'HEAD', 'refs/heads/main'], $remote);

        return $remote;
    }

    private function fileUrl(string $path): string
    {
        $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return 'file:///' . $normalized;
        }

        return 'file://' . $normalized;
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-provider-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);

        return $root;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child) && !is_link($child)) {
                $this->removeDirectory($child);
            } else {
                @unlink($child);
            }
        }

        @rmdir($path);
    }
}
