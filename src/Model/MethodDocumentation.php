<?php

declare(strict_types=1);

namespace SourceSlate\Model;

use SourceSlate\PhpDoc\Model\PhpDocBlock;

final readonly class MethodDocumentation
{
    /**
     * @param list<string> $parameters
     * @param list<AttributeDocumentation> $attributes
     */
    public function __construct(
        public string $name,
        public string $visibility,
        public bool $static,
        public array $parameters,
        public ?string $returnType,
        public int $line,
        public ?PhpDocBlock $phpDoc = null,
        public array $attributes = [],
    ) {
    }
}
