<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Build;

use PHPUnit\Framework\TestCase;
use SourceSlate\Build\DocumentationComparator;

final class DocumentationComparatorTest extends TestCase
{
    public function testIdenticalTreesMatch(): void
    {
        [$expected, $actual, $root] = $this->trees();
        file_put_contents($expected . DIRECTORY_SEPARATOR . 'index.html', 'same');
        file_put_contents($actual . DIRECTORY_SEPARATOR . 'index.html', 'same');

        try {
            $result = (new DocumentationComparator())->compare($expected, $actual);
            self::assertTrue($result['matches']);
            self::assertSame([], $result['missing']);
            self::assertSame([], $result['unexpected']);
            self::assertSame([], $result['changed']);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testMissingUnexpectedAndChangedFilesAreReportedDeterministically(): void
    {
        [$expected, $actual, $root] = $this->trees();
        mkdir($expected . DIRECTORY_SEPARATOR . 'api');
        mkdir($actual . DIRECTORY_SEPARATOR . 'api');
        file_put_contents($expected . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'changed.html', 'new');
        file_put_contents($actual . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'changed.html', 'old');
        file_put_contents($expected . DIRECTORY_SEPARATOR . 'z-missing.html', 'missing');
        file_put_contents($expected . DIRECTORY_SEPARATOR . 'a-missing.html', 'missing');
        file_put_contents($actual . DIRECTORY_SEPARATOR . 'z-unexpected.html', 'unexpected');
        file_put_contents($actual . DIRECTORY_SEPARATOR . 'a-unexpected.html', 'unexpected');

        try {
            $result = (new DocumentationComparator())->compare($expected, $actual);

            self::assertFalse($result['matches']);
            self::assertSame(['a-missing.html', 'z-missing.html'], $result['missing']);
            self::assertSame(['a-unexpected.html', 'z-unexpected.html'], $result['unexpected']);
            self::assertSame(['api/changed.html'], $result['changed']);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testManifestDifferencesAreIgnored(): void
    {
        [$expected, $actual, $root] = $this->trees();
        file_put_contents($expected . DIRECTORY_SEPARATOR . 'index.html', 'same');
        file_put_contents($actual . DIRECTORY_SEPARATOR . 'index.html', 'same');
        file_put_contents($expected . DIRECTORY_SEPARATOR . '.sourceslate-manifest.json', '{"commit":"a"}');
        file_put_contents($actual . DIRECTORY_SEPARATOR . '.sourceslate-manifest.json', '{"commit":"b"}');

        try {
            $result = (new DocumentationComparator())->compare($expected, $actual);
            self::assertTrue($result['matches']);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testMissingActualDirectoryTreatsExpectedFilesAsMissing(): void
    {
        $root = $this->temporaryDirectory();
        $expected = $root . DIRECTORY_SEPARATOR . 'expected';
        $actual = $root . DIRECTORY_SEPARATOR . 'actual';
        mkdir($expected);
        file_put_contents($expected . DIRECTORY_SEPARATOR . 'index.html', 'content');

        try {
            $result = (new DocumentationComparator())->compare($expected, $actual);
            self::assertFalse($result['matches']);
            self::assertSame(['index.html'], $result['missing']);
        } finally {
            $this->removeDirectory($root);
        }
    }

    /** @return array{0:string,1:string,2:string} */
    private function trees(): array
    {
        $root = $this->temporaryDirectory();
        $expected = $root . DIRECTORY_SEPARATOR . 'expected';
        $actual = $root . DIRECTORY_SEPARATOR . 'actual';
        mkdir($expected);
        mkdir($actual);

        return [$expected, $actual, $root];
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-compare-' . bin2hex(random_bytes(6));
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
