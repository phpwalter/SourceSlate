<?php

declare(strict_types=1);

namespace SourceSlate\Model;

final readonly class SymbolReference
{
    public function __construct(
        public string $kind,
        public string $qualifiedName,
        public string $url,
        public string $sourcePath,
        public int $line,
    ) {
    }
}
