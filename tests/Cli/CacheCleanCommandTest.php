<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SourceSlate\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

final class CacheCleanCommandTest extends TestCase
{
    private string|false $previousCacheDirectory;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCacheDirectory = getenv('SOURCESLATE_CACHE_DIR');
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-cache-clean-' . bin2hex(random_bytes(6));
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

    public function testDryRunReportsButPreservesOldRepairArtifact(): void
    {
        $repair = $this->root . DIRECTORY_SEPARATOR . 'abc.repair-old';
        mkdir($repair, 0777, true);
        touch($repair, time() - 172800);

        [$status, $display] = $this->run(['command' => 'cache:clean', '--older-than' => '24h', '--dry-run' => true]);

        self::assertSame(0, $status);
        self::assertStringContainsString('Would remove:', $display);
        self::assertDirectoryExists($repair);
    }

    public function testRemovesOldRepairArtifactsAndReleasedLocks(): void
    {
        $repair = $this->root . DIRECTORY_SEPARATOR . 'abc.repair-old';
        mkdir($repair, 0777, true);
        file_put_contents($repair . DIRECTORY_SEPARATOR . 'partial.txt', 'partial');
        touch($repair, time() - 172800);

        $lock = $this->root . '.locks' . DIRECTORY_SEPARATOR . 'repositories' . DIRECTORY_SEPARATOR . 'abc.lock';
        mkdir(dirname($lock), 0777, true);
        file_put_contents($lock, '{}');
        touch($lock, time() - 172800);

        [$status] = $this->run(['command' => 'cache:clean', '--older-than' => '24h']);

        self::assertSame(0, $status);
        self::assertDirectoryDoesNotExist($repair);
        self::assertFileDoesNotExist($lock);
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
