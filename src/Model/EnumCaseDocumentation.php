<?php

declare(strict_types=1);

namespace SourceSlate\Model;

use SourceSlate\PhpDoc\Model\PhpDocBlock;

final readonly class EnumCaseDocumentation
{
    /** @param list<AttributeDocumentation> $attributes */
    public function __construct(
        public string $name,
        public int $line,
        public ?string $value = null,
        public ?PhpDocBlock $phpDoc = null,
        public array $attributes = [],
    ) {
    }
}
