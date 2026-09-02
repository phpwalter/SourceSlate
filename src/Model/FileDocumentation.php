<?php

declare(strict_types=1);

namespace SourceSlate\Model;

use SourceSlate\PhpDoc\Model\PhpDocBlock;

final readonly class FileDocumentation
{
    /**
     * @param list<non-empty-string> $declaredSymbols
     * @param list<TypeDocumentation> $types
     * @param list<FunctionDocumentation> $functions
     */
    public function __construct(
        public string $path,
        public array $declaredSymbols,
        public ?string $summary = null,
        public ?PhpDocBlock $phpDoc = null,
        public array $types = [],
        public string $sourceCode = '',
        public array $functions = [],
    ) {
    }
}
