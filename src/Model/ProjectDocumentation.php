<?php

/**
 * @file ProjectDocumentation.php
 * @path src/Model/ProjectDocumentation.php
 * @version 1.0.0
 * @date 2026-09-02
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Represents the normalized project-level documentation model rendered by SourceSlate.
 */

declare(strict_types=1);

namespace SourceSlate\Model;

/**
 * Stores the immutable normalized documentation model for one project build.
 */
final readonly class ProjectDocumentation
{
    /**
     * @param non-empty-string $name Human-readable project name.
     * @param list<FileDocumentation> $files Parsed source files in deterministic path order.
     */
    public function __construct(
        public string $name,
        public array $files,
    ) {
    }
}
