<?php

/**
 * @file FileDocumentation.php
 * @path src/Model/FileDocumentation.php
 * @version 1.0.0
 * @date 2026-09-02
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Represents documentation metadata extracted from one PHP source file.
 */

declare(strict_types=1);

namespace SourceSlate\Model;

/**
 * Represents one parsed PHP source file in the documentation model.
 *
 * This initial model intentionally captures stable file-level facts only.
 * Type/member relationships will be layered on without changing renderer contracts.
 */
final readonly class FileDocumentation
{
    /**
     * @param non-empty-string $path Project-relative source path.
     * @param list<non-empty-string> $declaredSymbols Fully-qualified class-like symbols declared by the file.
     */
    public function __construct(
        public string $path,
        public array $declaredSymbols,
        public ?string $summary = null,
    ) {
    }
}
