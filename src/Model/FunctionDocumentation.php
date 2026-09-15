<?php

declare(strict_types=1);

namespace SourceSlate\Model;

use SourceSlate\PhpDoc\Model\PhpDocBlock;

final readonly class FunctionDocumentation
{
    /**
     * @param list<string> $parameters
     * @param list<AttributeDocumentation> $attributes
     */
    public function __construct(
        public string $name,
        public string $fullyQualifiedName,
        public string $namespace,
        public string $sourcePath,
        public array $parameters,
        public ?string $returnType,
        public int $line,
        public ?PhpDocBlock $phpDoc = null,
        public array $attributes = [],
    ) {
    }
}
