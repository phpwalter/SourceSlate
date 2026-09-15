<?php

declare(strict_types=1);

namespace Example;

/**
 * Demonstrates the PHP constructs SourceSlate documents.
 *
 * @since 1.0.0
 * @see ExampleState
 */
final readonly class ExampleService implements ServiceContract
{
    public const VERSION = '1.0';

    /**
     * @param list<string> $labels Labels associated with the service.
     */
    public function __construct(
        public int $id,
        private array $labels = [],
    ) {
    }

    /**
     * Return a normalized display name.
     *
     * @param non-empty-string $name Input name.
     * @return non-empty-string
     */
    public function normalize(string $name): string
    {
        return trim($name);
    }
}

interface ServiceContract
{
    public function normalize(string $name): string;
}

trait Identified
{
    public function identifier(): int
    {
        return $this->id;
    }
}

enum ExampleState: string
{
    case Ready = 'ready';
    case Archived = 'archived';
}

/** Return whether the example feature is enabled. */
function example_enabled(bool $default = true): bool
{
    return $default;
}
