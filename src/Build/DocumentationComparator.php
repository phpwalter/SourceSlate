<?php

declare(strict_types=1);

namespace SourceSlate\Build;

final readonly class DocumentationComparator
{
    public function compare(string $expectedDirectory, string $actualDirectory): array
    {
        $expected = $this->hashTree($expectedDirectory);
        $actual = $this->hashTree($actualDirectory);

        $missing = array_values(array_diff(array_keys($expected), array_keys($actual)));
        $unexpected = array_values(array_diff(array_keys($actual), array_keys($expected)));
        $changed = [];

        foreach (array_intersect(array_keys($expected), array_keys($actual)) as $path) {
            if ($expected[$path] !== $actual[$path]) {
                $changed[] = $path;
            }
        }

        sort($missing, SORT_STRING);
        sort($unexpected, SORT_STRING);
        sort($changed, SORT_STRING);

        return [
            'matches' => $missing === [] && $unexpected === [] && $changed === [],
            'missing' => $missing,
            'unexpected' => $unexpected,
            'changed' => $changed,
        ];
    }

    private function hashTree(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $hashes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getFilename() === '.sourceslate-manifest.json') {
                continue;
            }

            $path = $file->getPathname();
            $relative = substr($path, strlen(rtrim($directory, '\\/')) + 1);
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            $hashes[$relative] = hash_file('sha256', $path);
        }

        ksort($hashes, SORT_STRING);

        return $hashes;
    }
}
