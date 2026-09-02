<?php

/**
 * @file ReturnTagHandler.php
 * @path src/PhpDoc/Tag/ReturnTagHandler.php
 * @version 1.0.0
 * @date 2026-05-20
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Interprets PHPDoc return tags using the shared PHPStan-parsed AST.
 */

declare(strict_types=1);

namespace SourceSlate\PhpDoc\Tag;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use SourceSlate\PhpDoc\Model\TagDocumentation;

final class ReturnTagHandler implements TagHandlerInterface
{
    public function supports(string $tagName): bool
    {
        return $tagName === 'return';
    }

    public function handle(string $tagName, PhpDocTagNode $tag): TagDocumentation
    {
        $value = $tag->value;
        if (!$value instanceof ReturnTagValueNode) {
            return new TagDocumentation($tagName, (string) $value, true);
        }

        return new TagDocumentation(
            $tagName,
            (string) $value,
            true,
            (string) $value->type,
            null,
            trim($value->description) !== '' ? trim($value->description) : null,
        );
    }
}
