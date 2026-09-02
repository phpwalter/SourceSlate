<?php

declare(strict_types=1);

namespace SourceSlate\Model;

use SourceSlate\PhpDoc\Model\PhpDocBlock;

final readonly class ConstantDocumentation
{
    public function __construct(
        public string $name,
        public string $visibility,
        public int $line,
        public ?PhpDocBlock $phpDoc = null,
    ) {
    }
}
