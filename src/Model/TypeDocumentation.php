<?php

declare(strict_types=1);

namespace SourceSlate\Model;

use SourceSlate\PhpDoc\Model\PhpDocBlock;

final readonly class TypeDocumentation
{
    /**
     * @param list<non-empty-string> $extends
     * @param list<non-empty-string> $implements
     * @param list<non-empty-string> $traits
     * @param list<MethodDocumentation> $methods
     * @param list<PropertyDocumentation> $properties
     * @param list<ConstantDocumentation> $constants
     * @param list<EnumCaseDocumentation> $enumCases
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
        public array $properties = [],
        public array $constants = [],
        public array $enumCases = [],
    ) {
    }
}
