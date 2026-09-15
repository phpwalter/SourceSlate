<?php

declare(strict_types=1);

namespace SourceSlate\Model;

use SourceSlate\PhpDoc\Model\PhpDocBlock;

final readonly class ConstantDocumentation
{
    /** @param list<AttributeDocumentation> $attributes */
    public function __construct(
        public string $name,
        public string $visibility,
        public int $line,
        public ?PhpDocBlock $phpDoc = null,
        public array $attributes = [],
    ) {
    }
}
