<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Source\Git;

use PHPUnit\Framework\TestCase;
use SourceSlate\Exception\GitException;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitClient;
use SourceSlate\Source\Git\GitSourceProvider;
use SourceSlate\Source\SourceRequest;

final class GitAbbreviatedShaIntegrationTest extends TestCase
{
    public function testResolvesUnambiguousAbbreviatedCommitSha(): void
    {
        $root = $this->temporaryDirectory();
        [$remote, $work, $git] = $this->createRemoteRepository($root);
        $commit = $git->run(['rev-parse', 'HEAD'], $work);
        $prefix = substr($commit, 0, 12);

        try {
            $workspace = $this->provider($root)->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
                ref: $prefix,
            ));

            self::assertSame($commit, $workspace->requestedRef);
            self::assertSame($commit, $workspace->resolvedCommit);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testRejectsAmbiguousAbbreviatedObjectSha(): void
    {
        $root = $this->temporaryDirectory();
        [$remote, $work, $git] = $this->createRemoteRepository($root);
        [$prefix, $first, $second] = $this->findBlobPrefixCollision();
        file_put_contents($work . DIRECTORY_SEPARATOR . 'first.txt', $first);
        file_put_contents($work . DIRECTORY_SEPARATOR . 'second.txt', $second);
        $git->run(['add', 'first.txt', 'second.txt'], $work);
        $git->run(['-c', 'user.name=SourceSlate Tests', '-c', 'user.email=sourceslate@example.test', 'commit', '-m', 'add colliding blobs'], $work);
        $git->run(['push', 'origin', 'main'], $work);

        try {
            $this->expectException(GitException::class);
            $this->expectExceptionMessage('is ambiguous; use a longer SHA');

            $this->provider($root)->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
                ref: $prefix,
            ));
        } finally {
            $this->removeDirectory($root);
        }
    }

    /** @return array{0:string,1:string,2:string} */
    private function findBlobPrefixCollision(): array
    {
        $seen = [];
        for ($i = 0; $i < 100000; ++$i) {
            $content = 'sourceslate-collision-' . $i . "\n";
            $sha = sha1('blob ' . strlen($content) . "\0" . $content);
            $prefix = substr($sha, 0, 7);
            if (isset($seen[$prefix]) && $seen[$prefix] !== $content) {
                return [$prefix, $seen[$prefix], $content];
            }
            $seen[$prefix] = $content;
        }

        self::fail('Unable to generate a 7-character Git blob SHA collision.');
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
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-abbrev-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        return realpath($root) ?: $root;
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
