<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Source\Git;

use PHPUnit\Framework\TestCase;
use SourceSlate\Exception\GitException;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitClient;
use SourceSlate\Source\Git\GitSourceProvider;
use SourceSlate\Source\SourceRequest;

final class GitSourceProviderSelectorsIntegrationTest extends TestCase
{
    public function testResolvesRepositoryDefaultBranchWithoutSelector(): void
    {
        $root = $this->temporaryDirectory();
        [$remote] = $this->createRemoteRepository($root);
        $provider = $this->provider($root);

        try {
            $workspace = $provider->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
            ));

            self::assertSame('main', $workspace->requestedRef);
            self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', (string) $workspace->resolvedCommit);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testResolvesExplicitTag(): void
    {
        $root = $this->temporaryDirectory();
        [$remote, $work, $git] = $this->createRemoteRepository($root);
        $git->run(['tag', 'v1.0.0'], $work);
        $git->run(['push', 'origin', 'v1.0.0'], $work);
        $expected = $git->run(['rev-parse', 'v1.0.0^{commit}'], $work);

        try {
            $workspace = $this->provider($root)->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
                tag: 'v1.0.0',
            ));

            self::assertSame('v1.0.0', $workspace->requestedRef);
            self::assertSame($expected, $workspace->resolvedCommit);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testResolvesFullCommitSha(): void
    {
        $root = $this->temporaryDirectory();
        [$remote, $work, $git] = $this->createRemoteRepository($root);
        $sha = $git->run(['rev-parse', 'HEAD'], $work);

        try {
            $workspace = $this->provider($root)->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
                ref: $sha,
            ));

            self::assertSame($sha, $workspace->requestedRef);
            self::assertSame($sha, $workspace->resolvedCommit);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testPlainRefRejectsAmbiguousBranchAndTagName(): void
    {
        $root = $this->temporaryDirectory();
        [$remote, $work, $git] = $this->createRemoteRepository($root);
        $git->run(['branch', 'release'], $work);
        $git->run(['tag', 'release'], $work);
        $git->run(['push', 'origin', 'refs/heads/release:refs/heads/release'], $work);
        $git->run(['push', 'origin', 'refs/tags/release:refs/tags/release'], $work);

        try {
            $this->expectException(GitException::class);
            $this->expectExceptionMessage('Ref "release" is ambiguous');

            $this->provider($root)->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
                ref: 'release',
            ));
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testRecursivelyInitializesSubmodulesWhenRequested(): void
    {
        $root = $this->temporaryDirectory();
        $git = new GitClient(30);
        $subWork = $root . DIRECTORY_SEPARATOR . 'sub-work';
        $subRemote = $root . DIRECTORY_SEPARATOR . 'submodule.git';
        mkdir($subWork, 0777, true);
        file_put_contents($subWork . DIRECTORY_SEPARATOR . 'Module.php', "<?php\nfinal class Module {}\n");
        $git->run(['init', '-b', 'main'], $subWork);
        $git->run(['add', '.'], $subWork);
        $git->run(['-c', 'user.name=SourceSlate Tests', '-c', 'user.email=sourceslate@example.test', 'commit', '-m', 'submodule'], $subWork);
        $git->run(['init', '--bare', $subRemote]);
        $git->run(['remote', 'add', 'origin', $this->fileUrl($subRemote)], $subWork);
        $git->run(['push', '-u', 'origin', 'main'], $subWork);
        $git->run(['symbolic-ref', 'HEAD', 'refs/heads/main'], $subRemote);

        [$remote, $work] = $this->createRemoteRepository($root . DIRECTORY_SEPARATOR . 'parent');
        $git->run(['-c', 'protocol.file.allow=always', 'submodule', 'add', $this->fileUrl($subRemote), 'vendor/module'], $work);
        $git->run(['add', '.'], $work);
        $git->run(['-c', 'user.name=SourceSlate Tests', '-c', 'user.email=sourceslate@example.test', 'commit', '-m', 'add submodule'], $work);
        $git->run(['push', 'origin', 'main'], $work);

        $previous = getenv('GIT_ALLOW_PROTOCOL');
        putenv('GIT_ALLOW_PROTOCOL=file');
        try {
            $workspace = $this->provider($root)->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
                branch: 'main',
                recurseSubmodules: true,
            ));

            self::assertFileExists($workspace->root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . 'Module.php');
        } finally {
            if ($previous === false) {
                putenv('GIT_ALLOW_PROTOCOL');
            } else {
                putenv('GIT_ALLOW_PROTOCOL=' . $previous);
            }
            $this->removeDirectory($root);
        }
    }

    /** @return array{0:string,1:string,2:GitClient} */
    private function createRemoteRepository(string $root): array
    {
        $git = new GitClient(30);
        $work = $root . DIRECTORY_SEPARATOR . 'work';
        $remote = $root . DIRECTORY_SEPARATOR . 'remote.git';

        mkdir($work . DIRECTORY_SEPARATOR . 'src', 0777, true);
        file_put_contents($work . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php', "<?php\nfinal class Example {}\n");
        $git->run(['init', '-b', 'main'], $work);
        $git->run(['add', '.'], $work);
        $git->run(['-c', 'user.name=SourceSlate Tests', '-c', 'user.email=sourceslate@example.test', 'commit', '-m', 'initial'], $work);
        $git->run(['init', '--bare', $remote]);
        $git->run(['remote', 'add', 'origin', $this->fileUrl($remote)], $work);
        $git->run(['push', '-u', 'origin', 'main'], $work);
        $git->run(['symbolic-ref', 'HEAD', 'refs/heads/main'], $remote);

        return [$remote, $work, $git];
    }

    private function provider(string $root): GitSourceProvider
    {
        return new GitSourceProvider(new GitClient(30), new GitCache($root . DIRECTORY_SEPARATOR . 'cache'));
    }

    private function fileUrl(string $path): string
    {
        $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        return preg_match('/^[A-Za-z]:\//', $normalized) === 1 ? 'file:///' . $normalized : 'file://' . $normalized;
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-selectors-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        return $root;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $child = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($child) && !is_link($child)) {
                $this->removeDirectory($child);
            } else {
                @unlink($child);
            }
        }
        @rmdir($path);
    }
}
