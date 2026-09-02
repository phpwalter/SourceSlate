<?php

/**
 * @file UnknownTagHandler.php
 * @path src/PhpDoc/Tag/UnknownTagHandler.php
 * @version 1.0.0
 * @date 2026-05-20
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Preserves unrecognized PHPDoc tags so SourceSlate never discards source metadata.
 */

declare(strict_types=1);

namespace SourceSlate\PhpDoc\Tag;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use SourceSlate\PhpDoc\Model\TagDocumentation;

final class UnknownTagHandler implements TagHandlerInterface
{
    public function supports(string $tagName): bool
    {
        return true;
    }

    public function handle(string $tagName, PhpDocTagNode $tag): TagDocumentation
    {
        return new TagDocumentation($tagName, (string) $tag->value, false);
    }
}
