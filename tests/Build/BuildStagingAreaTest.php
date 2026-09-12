<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Build;

use PHPUnit\Framework\TestCase;
use SourceSlate\Build\BuildStagingArea;

final class BuildStagingAreaTest extends TestCase
{
    public function testPublishMovesStagingIntoDestination(): void
    {
        $root = $this->temporaryDirectory();
        $destination = $root . DIRECTORY_SEPARATOR . 'docs';
        $staging = new BuildStagingArea($destination);
        file_put_contents($staging->path() . DIRECTORY_SEPARATOR . 'index.html', 'new');

        try {
            $stagingPath = $staging->path();
            $staging->publish();

            self::assertDirectoryDoesNotExist($stagingPath);
            self::assertFileExists($destination . DIRECTORY_SEPARATOR . 'index.html');
            self::assertSame('new', file_get_contents($destination . DIRECTORY_SEPARATOR . 'index.html'));
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testPublishReplacesExistingDestinationAndRemovesBackup(): void
    {
        $root = $this->temporaryDirectory();
        $destination = $root . DIRECTORY_SEPARATOR . 'docs';
        mkdir($destination);
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'index.html', 'old');

        $staging = new BuildStagingArea($destination);
        file_put_contents($staging->path() . DIRECTORY_SEPARATOR . 'index.html', 'new');

        try {
            $staging->publish();

            self::assertSame('new', file_get_contents($destination . DIRECTORY_SEPARATOR . 'index.html'));
            self::assertSame([], glob($destination . '.sourceslate-previous-*') ?: []);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testConstructorRestoresInterruptedPublicationBackupWhenDestinationIsMissing(): void
    {
        $root = $this->temporaryDirectory();
        $destination = $root . DIRECTORY_SEPARATOR . 'docs';
        $backup = $destination . '.sourceslate-previous-deadbeef';
        mkdir($backup);
        file_put_contents($backup . DIRECTORY_SEPARATOR . 'index.html', 'last-known-good');

        try {
            $staging = new BuildStagingArea($destination);

            self::assertDirectoryExists($destination);
            self::assertSame('last-known-good', file_get_contents($destination . DIRECTORY_SEPARATOR . 'index.html'));
            self::assertDirectoryDoesNotExist($backup);
            $staging->discard();
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testConstructorKeepsNewestBackupWhenRecoveringMultipleInterruptedBackups(): void
    {
        $root = $this->temporaryDirectory();
        $destination = $root . DIRECTORY_SEPARATOR . 'docs';
        $older = $destination . '.sourceslate-previous-old';
        $newer = $destination . '.sourceslate-previous-new';
        mkdir($older);
        mkdir($newer);
        file_put_contents($older . DIRECTORY_SEPARATOR . 'index.html', 'older');
        file_put_contents($newer . DIRECTORY_SEPARATOR . 'index.html', 'newer');
        touch($older, time() - 10);
        touch($newer, time());

        try {
            $staging = new BuildStagingArea($destination);

            self::assertSame('newer', file_get_contents($destination . DIRECTORY_SEPARATOR . 'index.html'));
            self::assertDirectoryDoesNotExist($older);
            self::assertDirectoryDoesNotExist($newer);
            $staging->discard();
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testConstructorRemovesObsoleteBackupsWhenDestinationAlreadyExists(): void
    {
        $root = $this->temporaryDirectory();
        $destination = $root . DIRECTORY_SEPARATOR . 'docs';
        $backup = $destination . '.sourceslate-previous-orphan';
        mkdir($destination);
        mkdir($backup);
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'index.html', 'published');
        file_put_contents($backup . DIRECTORY_SEPARATOR . 'index.html', 'obsolete');

        try {
            $staging = new BuildStagingArea($destination);

            self::assertSame('published', file_get_contents($destination . DIRECTORY_SEPARATOR . 'index.html'));
            self::assertDirectoryDoesNotExist($backup);
            $staging->discard();
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testDiscardRemovesStagingWithoutTouchingDestination(): void
    {
        $root = $this->temporaryDirectory();
        $destination = $root . DIRECTORY_SEPARATOR . 'docs';
        mkdir($destination);
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'index.html', 'published');

        $staging = new BuildStagingArea($destination);
        file_put_contents($staging->path() . DIRECTORY_SEPARATOR . 'index.html', 'temporary');

        try {
            $stagingPath = $staging->path();
            $staging->discard();

            self::assertDirectoryDoesNotExist($stagingPath);
            self::assertSame('published', file_get_contents($destination . DIRECTORY_SEPARATOR . 'index.html'));
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testConstructorCreatesMissingParentDirectories(): void
    {
        $root = $this->temporaryDirectory();
        $destination = $root . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'docs';

        try {
            $staging = new BuildStagingArea($destination);
            self::assertDirectoryExists(dirname($destination));
            self::assertDirectoryExists($staging->path());
            $staging->discard();
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-staging-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        return $root;
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
