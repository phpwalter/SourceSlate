<?php

declare(strict_types=1);

namespace SourceSlate\PhpDoc\Tag;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use SourceSlate\PhpDoc\Model\TagDocumentation;

final class StandardTagHandler implements TagHandlerInterface
{
    /** @var list<string> */
    private const SIMPLE_TAGS = [
        'deprecated', 'since', 'see', 'link', 'example', 'internal', 'inheritdoc', 'inheritDoc',
        'author', 'copyright', 'license', 'version', 'package', 'subpackage', 'api',
    ];

    /** @var list<string> */
    private const TYPE_TAGS = [
        'var', 'property', 'property-read', 'property-write', 'method',
        'template', 'template-covariant', 'template-contravariant',
        'extends', 'implements', 'use', 'mixin',
    ];

    public function supports(string $tagName): bool
    {
        return in_array($tagName, self::SIMPLE_TAGS, true)
            || in_array($tagName, self::TYPE_TAGS, true);
    }

    public function handle(string $tagName, PhpDocTagNode $tag): TagDocumentation
    {
        $raw = trim((string) $tag->value);
        if (in_array($tagName, self::SIMPLE_TAGS, true)) {
            return new TagDocumentation(
                name: $tagName,
                rawValue: $raw,
                known: true,
                description: $raw !== '' ? $raw : null,
            );
        }

        [$type, $subject, $description] = $this->splitStructuredValue($raw);
        return new TagDocumentation(
            name: $tagName,
            rawValue: $raw,
            known: true,
            type: $type,
            subject: $subject,
            description: $description,
        );
    }

    /** @return array{0:?string,1:?string,2:?string} */
    private function splitStructuredValue(string $raw): array
    {
        if ($raw === '') {
            return [null, null, null];
        }

        $parts = preg_split('/\s+/', $raw, 3) ?: [];
        $type = $parts[0] ?? null;
        $subject = $parts[1] ?? null;
        $description = $parts[2] ?? null;

        if ($subject !== null && !str_starts_with($subject, '$') && !str_contains($subject, '::')) {
            $description = trim(($subject . ' ' . ($description ?? '')));
            $subject = null;
        }

        return [$type, $subject, $description !== '' ? $description : null];
    }
}
