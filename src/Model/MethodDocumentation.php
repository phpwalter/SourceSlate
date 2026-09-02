<?php

/**
 * @file MethodDocumentation.php
 * @path src/Model/MethodDocumentation.php
 * @version 1.0.0
 * @date 2026-09-02
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Represents a documented method declaration independently of parser and renderer implementations.
 */

declare(strict_types=1);

namespace SourceSlate\Model;

use SourceSlate\PhpDoc\Model\PhpDocBlock;

/**
 * Stores the stable source-level contract SourceSlate needs to render a method.
 */
final readonly class MethodDocumentation
{
    /**
     * @param non-empty-string $name Method name as declared in source.
     * @param 'public'|'protected'|'private' $visibility Native method visibility.
     * @param list<string> $parameters Render-ready native parameter declarations in source order.
     * @param positive-int $line One-based declaration line in the source file.
     */
    public function __construct(
        public string $name,
        public string $visibility,
        public bool $static,
        public array $parameters,
        public ?string $returnType,
        public int $line,
        public ?PhpDocBlock $phpDoc = null,
    ) {
    }
}
