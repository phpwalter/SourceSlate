<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SourceSlate\Application;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitCacheMetadata;
use SourceSlate\Source\Git\GitRepositoryIdentity;
use Symfony\Component\Console\Tester\ApplicationTester;

final class CacheJsonOutputTest extends TestCase
{
    private string|false $previousCacheDirectory;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCacheDirectory = getenv('SOURCESLATE_CACHE_DIR');
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-cache-json-' . bin2hex(random_bytes(6));
        putenv('SOURCESLATE_CACHE_DIR=' . $this->root);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
        $this->removeDirectory($this->root . '.locks');
        if ($this->previousCacheDirectory === false) {
            putenv('SOURCESLATE_CACHE_DIR');
        } else {
            putenv('SOURCESLATE_CACHE_DIR=' . $this->previousCacheDirectory);
        }
        parent::tearDown();
    }

    public function testListJsonReturnsRepositoryArray(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/example.git');
        GitCache::default()->saveMetadata(new GitCacheMetadata(
            identity: $identity,
            lastResolvedRef: 'refs/heads/main',
            lastResolvedCommit: str_repeat('a', 40),
            createdAt: '2026-09-15T00:00:00+00:00',
            lastUsedAt: '2026-09-15T00:00:00+00:00',
        ));

        [$status, $payload] = $this->runJson(['command' => 'cache:list', '--json' => true]);
        self::assertSame(0, $status);
        self::assertSame('success', $payload['status']);
        self::assertSame(1, $payload['count']);
        self::assertSame('github.com/acme/example', $payload['repositories'][0]['repository']);
    }

    public function testClearJsonReturnsStableConfirmationError(): void
    {
        [$status, $payload] = $this->runJson(['command' => 'cache:clear', '--json' => true]);
        self::assertSame(24, $status);
        self::assertSame('error', $payload['status']);
        self::assertSame('SS-CACHE-0010', $payload['code']);
        self::assertSame(24, $payload['exit_code']);
    }

    public function testPruneDryRunJsonReportsSelectionWithoutDeleting(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/acme/old.git');
        $cache = GitCache::default();
        $cache->saveMetadata(new GitCacheMetadata(
            identity: $identity,
            createdAt: '2020-01-01T00:00:00+00:00',
            lastUsedAt: '2020-01-01T00:00:00+00:00',
        ));
        $directory = $cache->repositoryDirectory($identity);

        [$status, $payload] = $this->runJson([
            'command' => 'cache:prune',
            '--older-than' => '1d',
            '--dry-run' => true,
            '--json' => true,
        ]);

        self::assertSame(0, $status);
        self::assertSame('dry-run', $payload['mode']);
        self::assertSame(1, $payload['count']);
        self::assertDirectoryExists($directory);
    }

    public function testRepairJsonReturnsStableConfirmationError(): void
    {
        [$status, $payload] = $this->runJson([
            'command' => 'cache:repair',
            'repository' => 'https://github.com/acme/example.git',
            '--json' => true,
        ]);

        self::assertSame(24, $status);
        self::assertSame('error', $payload['status']);
        self::assertSame('SS-CACHE-0010', $payload['code']);
    }

    public function testCleanDryRunJsonReturnsCandidateList(): void
    {
        $candidate = $this->root . DIRECTORY_SEPARATOR . 'abc.repair-stale';
        mkdir($candidate, 0777, true);
        touch($candidate, time() - 172800);

        [$status, $payload] = $this->runJson([
            'command' => 'cache:clean',
            '--older-than' => '24h',
            '--dry-run' => true,
            '--json' => true,
        ]);

        self::assertSame(0, $status);
        self::assertSame('dry-run', $payload['mode']);
        self::assertSame(1, $payload['count']);
        self::assertSame([$candidate], $payload['candidates']);
        self::assertDirectoryExists($candidate);
    }

    /** @return array{0:int,1:array<string,mixed>} */
    private function runJson(array $input): array
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(true);
        $tester = new ApplicationTester($application);
        $status = $tester->run($input, ['capture_stderr_separately' => false]);
        $payload = json_decode($tester->getDisplay(true), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        return [$status, $payload];
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
