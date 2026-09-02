<?php

/**
 * @file SourceSlateTagHandler.php
 * @path src/PhpDoc/Tag/SourceSlateTagHandler.php
 * @version 1.0.0
 * @date 2026-05-20
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Interprets SourceSlate's documentation-link tag without overloading standard tags.
 */

declare(strict_types=1);

namespace SourceSlate\PhpDoc\Tag;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use SourceSlate\PhpDoc\Model\TagDocumentation;

/**
 * Handles the SourceSlate-owned `@sourceslate` file-header link.
 */
final class SourceSlateTagHandler implements TagHandlerInterface
{
    public function supports(string $tagName): bool
    {
        return $tagName === 'sourceslate';
    }

    public function handle(string $tagName, PhpDocTagNode $tag): TagDocumentation
    {
        $target = trim((string) $tag->value);

        return new TagDocumentation(
            $tagName,
            (string) $tag->value,
            true,
            null,
            $target !== '' ? $target : null,
        );
    }
}
