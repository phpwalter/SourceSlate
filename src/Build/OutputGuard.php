<?php

declare(strict_types=1);

namespace SourceSlate\Build;

final readonly class OutputGuard
{
    public function assertWritable(string $outputDirectory, bool $force = false): void
    {
        if (!file_exists($outputDirectory)) {
            return;
        }

        if (!is_dir($outputDirectory)) {
            throw new \InvalidArgumentException(sprintf('Output path is not a directory: %s', $outputDirectory));
        }

        $entries = scandir($outputDirectory);
        if ($entries === false) {
            throw new \RuntimeException(sprintf('Unable to inspect output directory: %s', $outputDirectory));
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

        throw new \InvalidArgumentException(sprintf(
            'Output directory contains unmanaged files and has no .sourceslate-manifest.json: %s. Use --force-output to allow replacement.',
            $outputDirectory,
        ));
    }
}
