<?php

declare(strict_types=1);

namespace SourceSlate\Model;

final readonly class AttributeDocumentation
{
    /** @param list<string> $arguments */
    public function __construct(
        public string $name,
        public array $arguments = [],
    ) {
    }

    public function signature(): string
    {
        return '#[' . $this->name . ($this->arguments !== [] ? '(' . implode(', ', $this->arguments) . ')' : '') . ']';
    }
}
