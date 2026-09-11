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

final class CacheCommandsIntegrationTest extends TestCase
{
    private string|false $previousCacheDirectory;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCacheDirectory = getenv('SOURCESLATE_CACHE_DIR');
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-cache-cli-' . bin2hex(random_bytes(6));
        putenv('SOURCESLATE_CACHE_DIR=' . $this->root);
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

    public function testCacheListReportsEmptyCache(): void
    {
        [$status, $display] = $this->run(['command' => 'cache:list']);
        self::assertSame(0, $status);
        self::assertStringContainsString('No cached repositories.', $display);
    }

    public function testCacheListAndInfoExposePersistedRepositoryMetadata(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/example.git');
        $cache = GitCache::default();
        $cache->saveMetadata(new GitCacheMetadata(
            identity: $identity,
            lastResolvedRef: 'refs/heads/main',
            lastResolvedCommit: str_repeat('a', 40),
            createdAt: '2026-09-11T12:00:00+00:00',
            lastFetchAt: '2026-09-11T12:01:00+00:00',
            lastUsedAt: '2026-09-11T12:02:00+00:00',
        ));

        [$listStatus, $listDisplay] = $this->run(['command' => 'cache:list']);
        self::assertSame(0, $listStatus);
        self::assertStringContainsString('github.com/acme/example', $listDisplay);
        self::assertStringContainsString('refs/heads/main', $listDisplay);
        self::assertStringContainsString(str_repeat('a', 12), $listDisplay);

        [$infoStatus, $infoDisplay] = $this->run(['command' => 'cache:info', 'repository' => 'https://github.com/acme/example.git']);
        self::assertSame(0, $infoStatus);
        $decoded = json_decode($infoDisplay, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('github.com/acme/example', $decoded['repository']['canonical_url']);
        self::assertSame(str_repeat('a', 40), $decoded['state']['last_resolved_commit']);
    }

    public function testCacheInfoReturnsStableDiagnosticForMissingRepository(): void
    {
        [$status, $display] = $this->run(['command' => 'cache:info', 'repository' => 'https://github.com/acme/missing.git']);
        self::assertSame(24, $status);
        self::assertStringContainsString('SS-CACHE-0404', $display);
    }

    public function testCacheClearRequiresExplicitConfirmation(): void
    {
        [$status, $display] = $this->run(['command' => 'cache:clear']);
        self::assertSame(24, $status);
        self::assertStringContainsString('SS-CACHE-0010', $display);
    }

    public function testCacheClearRemovesConfirmedCache(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/example.git');
        GitCache::default()->saveMetadata(GitCacheMetadata::create($identity));

        [$status, $display] = $this->run(['command' => 'cache:clear', '--yes' => true]);
        self::assertSame(0, $status);
        self::assertStringContainsString('SourceSlate Git cache cleared.', $display);
        self::assertDirectoryDoesNotExist($this->root);
    }

    public function testCacheClearRefusesToDeleteActiveEntry(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/active.git');
        $cache = GitCache::default();
        $cache->saveMetadata(GitCacheMetadata::create($identity));
        $lock = $cache->lock($identity);
        $lock->acquire();

        try {
            [$status, $display] = $this->run(['command' => 'cache:clear', '--yes' => true]);
            self::assertSame(24, $status);
            self::assertStringContainsString('SS-CACHE-0025', $display);
            self::assertDirectoryExists($cache->repositoryDirectory($identity));
        } finally {
            $lock->release();
        }
    }

    public function testCachePruneDryRunDoesNotDeleteSelectedEntry(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/old.git');
        $cache = GitCache::default();
        $cache->saveMetadata(new GitCacheMetadata(identity: $identity, createdAt: '2020-01-01T00:00:00+00:00', lastUsedAt: '2020-01-01T00:00:00+00:00'));
        $directory = $cache->repositoryDirectory($identity);

        [$status, $display] = $this->run(['command' => 'cache:prune', '--older-than' => '1d', '--dry-run' => true]);
        self::assertSame(0, $status);
        self::assertStringContainsString('Would prune:', $display);
        self::assertDirectoryExists($directory);
    }

    public function testCachePruneDeletesExpiredEntry(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/old.git');
        $cache = GitCache::default();
        $cache->saveMetadata(new GitCacheMetadata(identity: $identity, createdAt: '2020-01-01T00:00:00+00:00', lastUsedAt: '2020-01-01T00:00:00+00:00'));
        $directory = $cache->repositoryDirectory($identity);

        [$status, $display] = $this->run(['command' => 'cache:prune', '--older-than' => '1d']);
        self::assertSame(0, $status);
        self::assertStringContainsString('Pruning:', $display);
        self::assertDirectoryDoesNotExist($directory);
    }

    public function testCacheVerifyReportsMissingBareRepositoryAsFailure(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/broken.git');
        GitCache::default()->saveMetadata(GitCacheMetadata::create($identity));

        [$status, $display] = $this->run(['command' => 'cache:verify']);
        self::assertSame(24, $status);
        self::assertStringContainsString('SS-CACHE-0201', $display);
        self::assertStringContainsString('SS-CACHE-0203', $display);
    }

    public function testCacheRepairRequiresConfirmation(): void
    {
        [$status, $display] = $this->run(['command' => 'cache:repair', 'repository' => 'https://github.com/acme/example.git']);
        self::assertSame(24, $status);
        self::assertStringContainsString('SS-CACHE-0010', $display);
    }

    public function testCacheRepairRefusesActiveEntry(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/active.git');
        $cache = GitCache::default();
        $cache->saveMetadata(GitCacheMetadata::create($identity));
        $lock = $cache->lock($identity);
        $lock->acquire();

        try {
            [$status, $display] = $this->run(['command' => 'cache:repair', 'repository' => 'https://github.com/acme/active.git', '--yes' => true]);
            self::assertSame(24, $status);
            self::assertStringContainsString('SS-CACHE-0025', $display);
        } finally {
            $lock->release();
        }
    }

    public function testCacheRepairRebuildsRepositoryFromLocalRemote(): void
    {
        $remote = $this->root . '-remote.git';
        $work = $this->root . '-work';
        mkdir($work, 0777, true);
        file_put_contents($work . DIRECTORY_SEPARATOR . 'Example.php', "<?php\nfinal class Example {}\n");
        $git = new GitClient(30);
        $git->run(['init', '-b', 'main'], $work);
        $git->run(['add', '.'], $work);
        $git->run(['-c', 'user.name=SourceSlate Tests', '-c', 'user.email=sourceslate@example.test', 'commit', '-m', 'initial'], $work);
        $git->run(['init', '--bare', $remote]);
        $git->run(['remote', 'add', 'origin', $this->fileUrl($remote)], $work);
        $git->run(['push', '-u', 'origin', 'main'], $work);

        try {
            [$status, $display] = $this->run(['command' => 'cache:repair', 'repository' => $this->fileUrl($remote), '--yes' => true]);
            self::assertSame(0, $status);
            self::assertStringContainsString('Rebuilt cache for', $display);
            $identity = GitRepositoryIdentity::fromUrl($this->fileUrl($remote));
            self::assertDirectoryExists(GitCache::default()->bareRepository($identity));
            self::assertFileExists(GitCache::default()->metadataPath($identity));
        } finally {
            $this->removeDirectory($remote);
            $this->removeDirectory($work);
        }
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
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
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
