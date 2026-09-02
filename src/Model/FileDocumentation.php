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
 * Represents documentation metadata and source content extracted from one PHP file.
 */

declare(strict_types=1);

namespace SourceSlate\Model;

use SourceSlate\PhpDoc\Model\PhpDocBlock;

/**
 * Represents one parsed PHP source file in the documentation model.
 *
 * Source text is retained so the renderer can generate a local source browser
 * without reopening or reparsing files. Type metadata remains parser-neutral.
 */
final readonly class FileDocumentation
{
    /**
     * @param non-empty-string $path Project-relative source path.
     * @param list<non-empty-string> $declaredSymbols Fully-qualified class-like symbols declared by the file.
     * @param list<TypeDocumentation> $types Class-like declarations in deterministic source order.
     */
    public function __construct(
        public string $path,
        public array $declaredSymbols,
        public ?string $summary = null,
        public ?PhpDocBlock $phpDoc = null,
        public array $types = [],
        public string $sourceCode = '',
    ) {
    }
}
