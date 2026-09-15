<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Build;

use PHPUnit\Framework\TestCase;
use SourceSlate\Build\BuildStagingArea;
use SourceSlate\Build\NativePublicationFilesystem;
use SourceSlate\Build\PublicationFilesystem;

final class BuildPublicationFailureTest extends TestCase
{
    public function testFailedPublishRestoresLastKnownGoodDocumentation(): void
    {
        $root = $this->temporaryDirectory();
        $destination = $root . DIRECTORY_SEPARATOR . 'docs';
        mkdir($destination);
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'index.html', 'last-known-good');

        $filesystem = new ScriptedPublicationFilesystem([2]);
        $staging = new BuildStagingArea($destination, $filesystem);
        file_put_contents($staging->path() . DIRECTORY_SEPARATOR . 'index.html', 'candidate');
        $stagingPath = $staging->path();

        try {
            try {
                $staging->publish();
                self::fail('Expected publication failure.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('Unable to publish SourceSlate output', $exception->getMessage());
            }

            self::assertDirectoryExists($destination);
            self::assertSame('last-known-good', file_get_contents($destination . DIRECTORY_SEPARATOR . 'index.html'));
            self::assertDirectoryExists($stagingPath);
            self::assertSame('candidate', file_get_contents($stagingPath . DIRECTORY_SEPARATOR . 'index.html'));
            self::assertSame([], glob($destination . '.sourceslate-previous-*') ?: []);
        } finally {
            $staging->discard();
            $this->removeDirectory($root);
        }
    }

    public function testFailedRollbackPreservesBackupAndReportsItsPath(): void
    {
        $root = $this->temporaryDirectory();
        $destination = $root . DIRECTORY_SEPARATOR . 'docs';
        mkdir($destination);
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'index.html', 'last-known-good');

        $filesystem = new ScriptedPublicationFilesystem([2, 3]);
        $staging = new BuildStagingArea($destination, $filesystem);
        file_put_contents($staging->path() . DIRECTORY_SEPARATOR . 'index.html', 'candidate');

        try {
            try {
                $staging->publish();
                self::fail('Expected rollback failure.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('unable to restore previous output', $exception->getMessage());
                $backups = glob($destination . '.sourceslate-previous-*') ?: [];
                self::assertCount(1, $backups);
                self::assertStringContainsString($backups[0], $exception->getMessage());
                self::assertSame('last-known-good', file_get_contents($backups[0] . DIRECTORY_SEPARATOR . 'index.html'));
            }

            self::assertDirectoryDoesNotExist($destination);
            self::assertDirectoryExists($staging->path());
        } finally {
            $staging->discard();
            foreach (glob($destination . '.sourceslate-previous-*') ?: [] as $backup) {
                $this->removeDirectory($backup);
            }
            $this->removeDirectory($root);
        }
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-publication-failure-' . bin2hex(random_bytes(6));
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

final class ScriptedPublicationFilesystem implements PublicationFilesystem
{
    private NativePublicationFilesystem $native;
    private int $renameCall = 0;

    /** @param list<int> $failRenameCalls */
    public function __construct(private readonly array $failRenameCalls)
    {
        $this->native = new NativePublicationFilesystem();
    }

    public function exists(string $path): bool
    {
        return $this->native->exists($path);
    }

    public function rename(string $from, string $to): bool
    {
        ++$this->renameCall;
        if (in_array($this->renameCall, $this->failRenameCalls, true)) {
            return false;
        }
        return $this->native->rename($from, $to);
    }

    public function unlink(string $path): bool
    {
        return $this->native->unlink($path);
    }
}
