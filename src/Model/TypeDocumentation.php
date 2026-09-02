<?php

/**
 * @file TypeDocumentation.php
 * @path src/Model/TypeDocumentation.php
 * @version 1.0.0
 * @date 2026-09-02
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Represents one class-like PHP declaration and its structural documentation relationships.
 */

declare(strict_types=1);

namespace SourceSlate\Model;

use SourceSlate\PhpDoc\Model\PhpDocBlock;

/**
 * Stores renderer-independent metadata for a class, interface, trait, or enum.
 *
 * Relationships are declaration-level only. Runtime call graphs and container
 * resolution are deliberately outside this model's 1.0 scope.
 */
final readonly class TypeDocumentation
{
    /**
     * @param non-empty-string $name Short declaration name.
     * @param non-empty-string $fullyQualifiedName Namespace-qualified declaration name without a leading backslash.
     * @param 'class'|'interface'|'trait'|'enum' $kind Declaration category.
     * @param non-empty-string $sourcePath Project-relative source path.
     * @param positive-int $line One-based declaration line in the source file.
     * @param list<non-empty-string> $extends Declared parent classes or interfaces.
     * @param list<non-empty-string> $implements Declared implemented interfaces.
     * @param list<non-empty-string> $traits Directly used traits in declaration order.
     * @param list<MethodDocumentation> $methods Methods in source declaration order.
     */
    public function __construct(
        public string $name,
        public string $fullyQualifiedName,
        public string $namespace,
        public string $kind,
        public string $sourcePath,
        public int $line,
        public array $extends,
        public array $implements,
        public array $traits,
        public array $methods,
        public ?PhpDocBlock $phpDoc = null,
    ) {
    }
}
