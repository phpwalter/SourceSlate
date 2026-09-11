<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Build;

use PHPUnit\Framework\TestCase;
use SourceSlate\Build\OutputGuard;
use SourceSlate\Exception\OutputException;

final class OutputGuardTest extends TestCase
{
    public function testMissingOutputDirectoryIsAllowed(): void
    {
        $root = $this->temporaryDirectory();
        $output = $root . DIRECTORY_SEPARATOR . 'docs';

        try {
            (new OutputGuard())->assertWritable($output);
            self::assertDirectoryDoesNotExist($output);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testEmptyOutputDirectoryIsAllowed(): void
    {
        $root = $this->temporaryDirectory();
        $output = $root . DIRECTORY_SEPARATOR . 'docs';
        mkdir($output);

        try {
            (new OutputGuard())->assertWritable($output);
            self::assertDirectoryExists($output);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testManagedOutputDirectoryIsAllowed(): void
    {
        $root = $this->temporaryDirectory();
        $output = $root . DIRECTORY_SEPARATOR . 'docs';
        mkdir($output);
        file_put_contents($output . DIRECTORY_SEPARATOR . 'index.html', 'old');
        file_put_contents($output . DIRECTORY_SEPARATOR . '.sourceslate-manifest.json', '{}');

        try {
            (new OutputGuard())->assertWritable($output);
            self::assertFileExists($output . DIRECTORY_SEPARATOR . 'index.html');
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testUnmanagedNonEmptyOutputDirectoryIsRejected(): void
    {
        $root = $this->temporaryDirectory();
        $output = $root . DIRECTORY_SEPARATOR . 'docs';
        mkdir($output);
        file_put_contents($output . DIRECTORY_SEPARATOR . 'keep.txt', 'important');

        try {
            $this->expectException(OutputException::class);
            $this->expectExceptionMessage('Output directory contains unmanaged files');

            (new OutputGuard())->assertWritable($output);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testForceAllowsUnmanagedOutputDirectory(): void
    {
        $root = $this->temporaryDirectory();
        $output = $root . DIRECTORY_SEPARATOR . 'docs';
        mkdir($output);
        file_put_contents($output . DIRECTORY_SEPARATOR . 'keep.txt', 'important');

        try {
            (new OutputGuard())->assertWritable($output, true);
            self::assertFileExists($output . DIRECTORY_SEPARATOR . 'keep.txt');
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testFileAtOutputPathIsRejected(): void
    {
        $root = $this->temporaryDirectory();
        $output = $root . DIRECTORY_SEPARATOR . 'docs';
        file_put_contents($output, 'not a directory');

        try {
            $this->expectException(OutputException::class);
            $this->expectExceptionMessage('Output path is not a directory');

            (new OutputGuard())->assertWritable($output);
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-output-' . bin2hex(random_bytes(6));
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
