<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Source\Git;

use PHPUnit\Framework\TestCase;
use SourceSlate\Exception\GitException;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitClient;
use SourceSlate\Source\Git\GitSourceProvider;
use SourceSlate\Source\SourceRequest;

final class GitSourceProviderRefSelectionTest extends TestCase
{
    public function testDefaultBranchIsResolvedFromRemoteHead(): void
    {
        $root = $this->temporaryDirectory();
        [$remote, $commit] = $this->createRemoteRepository($root);
        $provider = $this->provider($root);

        try {
            $workspace = $provider->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
            ));

            self::assertSame($commit, $workspace->resolvedCommit);
            self::assertSame('main', $workspace->requestedRef);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testTagSelectionResolvesTaggedCommit(): void
    {
        $root = $this->temporaryDirectory();
        [$remote, $commit, $work] = $this->createRemoteRepository($root);
        $git = new GitClient(30);
        $git->run(['tag', 'v1.2.3'], $work);
        $git->run(['push', 'origin', 'v1.2.3'], $work);

        try {
            $workspace = $this->provider($root)->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
                tag: 'v1.2.3',
            ));

            self::assertSame($commit, $workspace->resolvedCommit);
            self::assertSame('v1.2.3', $workspace->requestedRef);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testFullCommitShaSelectionIsExact(): void
    {
        $root = $this->temporaryDirectory();
        [$remote, $commit] = $this->createRemoteRepository($root);

        try {
            $workspace = $this->provider($root)->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
                ref: $commit,
            ));

            self::assertSame($commit, $workspace->resolvedCommit);
            self::assertSame($commit, $workspace->requestedRef);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testPlainRefRejectsBranchTagAmbiguity(): void
    {
        $root = $this->temporaryDirectory();
        [$remote, , $work] = $this->createRemoteRepository($root);
        $git = new GitClient(30);
        $git->run(['branch', 'release'], $work);
        $git->run(['tag', 'release'], $work);
        $git->run(['push', 'origin', 'refs/heads/release:refs/heads/release'], $work);
        $git->run(['push', 'origin', 'refs/tags/release'], $work);

        try {
            $this->expectException(GitException::class);
            $this->expectExceptionMessage('both refs/heads/release and refs/tags/release exist');

            $this->provider($root)->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
                ref: 'release',
            ));
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testExplicitBranchDisambiguatesBranchTagCollision(): void
    {
        $root = $this->temporaryDirectory();
        [$remote, $initialCommit, $work] = $this->createRemoteRepository($root);
        $git = new GitClient(30);
        $git->run(['tag', 'release', $initialCommit], $work);
        file_put_contents($work . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php', "<?php\nfinal class Example { public const VERSION = 2; }\n");
        $git->run(['add', '.'], $work);
        $git->run(['-c', 'user.name=SourceSlate Tests', '-c', 'user.email=sourceslate@example.test', 'commit', '-m', 'release branch'], $work);
        $branchCommit = $git->run(['rev-parse', 'HEAD'], $work);
        $git->run(['branch', 'release'], $work);
        $git->run(['push', 'origin', 'refs/heads/release:refs/heads/release'], $work);
        $git->run(['push', 'origin', 'refs/tags/release'], $work);

        try {
            $branchWorkspace = $this->provider($root)->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs-branch',
                branch: 'release',
            ));
            self::assertSame($branchCommit, $branchWorkspace->resolvedCommit);

            $tagWorkspace = $this->provider($root)->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs-tag',
                tag: 'release',
            ));
            self::assertSame($initialCommit, $tagWorkspace->resolvedCommit);
        } finally {
            $this->removeDirectory($root);
        }
    }

    /** @return array{0:string,1:string,2:string} */
    private function createRemoteRepository(string $root): array
    {
        $git = new GitClient(30);
        $work = $root . DIRECTORY_SEPARATOR . 'work';
        $remote = $root . DIRECTORY_SEPARATOR . 'remote.git';
        mkdir($work . DIRECTORY_SEPARATOR . 'src', 0777, true);
        file_put_contents($work . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php', "<?php\nfinal class Example { public const VERSION = 1; }\n");
        $git->run(['init', '-b', 'main'], $work);
        $git->run(['add', '.'], $work);
        $git->run(['-c', 'user.name=SourceSlate Tests', '-c', 'user.email=sourceslate@example.test', 'commit', '-m', 'initial'], $work);
        $commit = $git->run(['rev-parse', 'HEAD'], $work);
        $git->run(['init', '--bare', $remote]);
        $git->run(['remote', 'add', 'origin', $this->fileUrl($remote)], $work);
        $git->run(['push', '-u', 'origin', 'main'], $work);
        $git->run(['symbolic-ref', 'HEAD', 'refs/heads/main'], $remote);
        return [$remote, $commit, $work];
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
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-ref-selection-' . bin2hex(random_bytes(6));
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
