<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SourceSlate\Application;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitCacheMetadata;
use SourceSlate\Source\Git\GitRepositoryIdentity;
use Symfony\Component\Console\Tester\ApplicationTester;

final class CacheFailureRecoveryTest extends TestCase
{
    private string|false $previousCacheDirectory;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCacheDirectory = getenv('SOURCESLATE_CACHE_DIR');
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-cache-recovery-' . bin2hex(random_bytes(6));
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

    public function testVerifyReportsMalformedMetadataBeforeRepositoryFailure(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/corrupt.git');
        $cache = GitCache::default();
        $cache->ensureRepositoryDirectory($identity);
        file_put_contents($cache->metadataPath($identity), '{not-json');

        [$status, $display] = $this->run(['command' => 'cache:verify']);

        self::assertSame(24, $status);
        self::assertStringContainsString('SS-CACHE-0202', $display);
        self::assertStringContainsString('Invalid metadata', $display);
        self::assertStringNotContainsString('SS-CACHE-0201', $display);
    }

    public function testVerifyRejectsMetadataStoredUnderWrongCacheKey(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/right.git');
        $wrongIdentity = GitRepositoryIdentity::fromUrl('https://github.com/acme/wrong.git');
        $directory = GitCache::default()->repositoryDirectory($wrongIdentity);
        mkdir($directory, 0777, true);
        file_put_contents(
            $directory . DIRECTORY_SEPARATOR . 'metadata.json',
            json_encode(GitCacheMetadata::create($identity)->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );

        [$status, $display] = $this->run(['command' => 'cache:verify']);

        self::assertSame(24, $status);
        self::assertStringContainsString('SS-CACHE-0202', $display);
        self::assertStringContainsString('does not match its cache directory', $display);
    }

    public function testFailedRepairPreservesExistingCacheEntry(): void
    {
        $missingRemote = $this->root . '-missing.git';
        $repository = $this->fileUrl($missingRemote);
        $identity = GitRepositoryIdentity::fromUrl($repository);
        $cache = GitCache::default();
        $cache->saveMetadata(new GitCacheMetadata(
            identity: $identity,
            createdAt: '2026-09-11T12:00:00+00:00',
            lastUsedAt: '2026-09-11T12:00:00+00:00',
        ));
        $directory = $cache->repositoryDirectory($identity);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'sentinel.txt', 'last-known-good');
        $metadataBefore = file_get_contents($cache->metadataPath($identity));

        [$status, $display] = $this->run([
            'command' => 'cache:repair',
            'repository' => $repository,
            '--yes' => true,
        ]);

        self::assertNotSame(0, $status);
        self::assertStringContainsString('git', strtolower($display));
        self::assertDirectoryExists($directory);
        self::assertSame('last-known-good', file_get_contents($directory . DIRECTORY_SEPARATOR . 'sentinel.txt'));
        self::assertSame($metadataBefore, file_get_contents($cache->metadataPath($identity)));
        self::assertSame([], glob($directory . '.repair-*') ?: []);
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
