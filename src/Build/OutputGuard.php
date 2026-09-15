<?php

declare(strict_types=1);

namespace SourceSlate\Build;

use SourceSlate\Exception\OutputException;

final readonly class OutputGuard
{
    public function assertWritable(string $outputDirectory, bool $force = false): void
    {
        if (is_link($outputDirectory)) {
            throw new OutputException(
                'SS-OUT-0042',
                sprintf('Output directory may not be a symbolic link: %s', $outputDirectory),
                42,
            );
        }

        if (!file_exists($outputDirectory)) {
            $this->assertNoSymlinkAncestor($outputDirectory);
            return;
        }

        if (!is_dir($outputDirectory)) {
            throw new OutputException(
                'SS-OUT-0040',
                sprintf('Output path is not a directory: %s', $outputDirectory),
                40,
            );
        }

        $this->assertNoSymlinkAncestor($outputDirectory);

        $entries = scandir($outputDirectory);
        if ($entries === false) {
            throw new OutputException(
                'SS-OUT-0040',
                sprintf('Unable to inspect output directory: %s', $outputDirectory),
                40,
            );
        }

        $entries = array_values(array_diff($entries, ['.', '..']));
        if ($entries === []) {
            return;
        }

        $manifest = $outputDirectory . DIRECTORY_SEPARATOR . '.sourceslate-manifest.json';
        if (is_file($manifest)) {
            return;
        }

        if ($force) {
            return;
        }

        throw new OutputException(
            'SS-OUT-0041',
            sprintf(
                'Output directory contains unmanaged files and has no .sourceslate-manifest.json: %s. Use --force-output to allow replacement.',
                $outputDirectory,
            ),
            41,
        );
    }

    private function assertNoSymlinkAncestor(string $path): void
    {
        $candidate = $path;
        while ($candidate !== dirname($candidate)) {
            if (file_exists($candidate) || is_link($candidate)) {
                if (is_link($candidate)) {
                    throw new OutputException(
                        'SS-OUT-0042',
                        sprintf('Output path traverses a symbolic link: %s', $candidate),
                        42,
                    );
                }
            }
            $candidate = dirname($candidate);
        }
    }
}
