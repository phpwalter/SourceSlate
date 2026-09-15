<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SourceSlate\Application;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitCacheMetadata;
use SourceSlate\Source\Git\GitClient;
use SourceSlate\Source\Git\GitRepositoryIdentity;
use Symfony\Component\Console\Tester\ApplicationTester;

final class CacheVerifyRecoveryTest extends TestCase
{
    private string|false $previousCacheDirectory;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCacheDirectory = getenv('SOURCESLATE_CACHE_DIR');
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-cache-recovery-' . bin2hex(random_bytes(6));
        putenv('SOURCESLATE_CACHE_DIR=' . $this->root . DIRECTORY_SEPARATOR . 'cache');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
        if ($this->previousCacheDirectory === false) {
            putenv('SOURCESLATE_CACHE_DIR');
        } else {
            putenv('SOURCESLATE_CACHE_DIR=' . $this->previousCacheDirectory);
        }
        parent::tearDown();
    }

    public function testJsonVerifyReportsLegacyMetadataWithoutFailingHealthyRepository(): void
    {
        [$identity, $cache, $remote] = $this->createCachedRepository('legacy');
        $metadata = GitCacheMetadata::create($identity)->toArray();
        unset($metadata['schema_version']);
        file_put_contents($cache->metadataPath($identity), json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        [$status, $display] = $this->run(['command' => 'cache:verify', '--json' => true]);
        $payload = json_decode($display, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $status);
        self::assertSame('success', $payload['status']);
        self::assertTrue($payload['entries'][0]['legacy_metadata']);
        self::assertSame(0, $payload['entries'][0]['schema_version']);
        self::assertSame(GitRepositoryIdentity::fromUrl($this->fileUrl($remote))->canonicalUrl, $payload['entries'][0]['origin']);
    }

    public function testVerifyRejectsBareRepositoryWhoseOriginDoesNotMatchMetadataIdentity(): void
    {
        [$identity, $cache] = $this->createCachedRepository('expected');
        $otherRemote = $this->createRemoteRepository('other');
        (new GitClient(30))->run([
            '--git-dir=' . $cache->bareRepository($identity),
            'remote',
            'set-url',
            'origin',
            $this->fileUrl($otherRemote),
        ]);

        [$status, $display] = $this->run(['command' => 'cache:verify', '--json' => true]);
        $payload = json_decode($display, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(24, $status);
        self::assertSame('SS-CACHE-0204', $payload['entries'][0]['code']);
        self::assertStringContainsString('does not match cache entry', $payload['entries'][0]['message']);
    }

    public function testVerifyRejectsInvalidCachedWorktree(): void
    {
        [$identity, $cache] = $this->createCachedRepository('worktree');
        $worktrees = $cache->repositoryDirectory($identity) . DIRECTORY_SEPARATOR . 'worktrees';
        $invalid = $worktrees . DIRECTORY_SEPARATOR . str_repeat('a', 40);
        mkdir($invalid, 0777, true);
        file_put_contents($invalid . DIRECTORY_SEPARATOR . 'README.txt', 'not a git worktree');

        [$status, $display] = $this->run(['command' => 'cache:verify', '--json' => true]);
        $payload = json_decode($display, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(24, $status);
        self::assertSame('SS-CACHE-0205', $payload['entries'][0]['code']);
        self::assertStringContainsString('missing its .git link', $payload['entries'][0]['message']);
    }

    /** @return array{0:GitRepositoryIdentity,1:GitCache,2:string} */
    private function createCachedRepository(string $name): array
    {
        $remote = $this->createRemoteRepository($name);
        $identity = GitRepositoryIdentity::fromUrl($this->fileUrl($remote));
        $cache = GitCache::default();
        $cache->ensureRepositoryDirectory($identity);
        $git = new GitClient(30);
        $git->run(['clone', '--bare', $this->fileUrl($remote), $cache->bareRepository($identity)]);
        $cache->saveMetadata(GitCacheMetadata::create($identity));
        return [$identity, $cache, $remote];
    }

    private function createRemoteRepository(string $name): string
    {
        $git = new GitClient(30);
        $work = $this->root . DIRECTORY_SEPARATOR . $name . '-work';
        $remote = $this->root . DIRECTORY_SEPARATOR . $name . '.git';
        mkdir($work, 0777, true);
        file_put_contents($work . DIRECTORY_SEPARATOR . 'Example.php', "<?php\nfinal class Example {}\n");
        $git->run(['init', '-b', 'main'], $work);
        $git->run(['add', '.'], $work);
        $git->run(['-c', 'user.name=SourceSlate Tests', '-c', 'user.email=sourceslate@example.test', 'commit', '-m', 'initial'], $work);
        $git->run(['init', '--bare', $remote]);
        $git->run(['remote', 'add', 'origin', $this->fileUrl($remote)], $work);
        $git->run(['push', '-u', 'origin', 'main'], $work);
        $git->run(['symbolic-ref', 'HEAD', 'refs/heads/main'], $remote);
        return $remote;
    }

    /** @return array{0:int,1:string} */
    private function run(array $input): array
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(true);
        $tester = new ApplicationTester($application);
        $status = $tester->run($input, ['capture_stderr_separately' => false]);
        return [$status, $tester->getDisplay(true)];
    }

    private function fileUrl(string $path): string
    {
        $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        return preg_match('/^[A-Za-z]:\//', $normalized) === 1 ? 'file:///' . $normalized : 'file://' . $normalized;
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
