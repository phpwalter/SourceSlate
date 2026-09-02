<?php

/**
 * @file Configuration.php
 * @path src/Configuration/Configuration.php
 * @version 1.0.0
 * @date 2026-09-02
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Defines the immutable normalized configuration consumed by SourceSlate builds.
 */

declare(strict_types=1);

namespace SourceSlate\Configuration;

/**
 * Stores normalized SourceSlate build configuration.
 *
 * Paths are retained exactly as normalized by ConfigurationLoader. The object
 * is immutable after construction and contains no environment or filesystem
 * behavior of its own.
 */
final readonly class Configuration
{
    /**
     * @param non-empty-string $projectName Human-readable project name.
     * @param non-empty-list<non-empty-string> $sourcePaths Source roots to scan.
     * @param list<non-empty-string> $excludePaths Relative paths excluded from scanning.
     * @param non-empty-string $outputPath Documentation output directory.
     * @param bool $updateSource Whether file headers may be updated with @sourceslate links.
     */
    public function __construct(
        public string $projectName,
        public array $sourcePaths,
        public array $excludePaths,
        public string $outputPath,
        public bool $updateSource = false,
    ) {
    }
}
