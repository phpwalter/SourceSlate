<?php

/**
 * @file TagHandlerInterface.php
 * @path src/PhpDoc/Tag/TagHandlerInterface.php
 * @version 1.0.0
 * @date 2026-05-20
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Defines the extension contract for semantic PHPDoc tag interpretation.
 */

declare(strict_types=1);

namespace SourceSlate\PhpDoc\Tag;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use SourceSlate\PhpDoc\Model\TagDocumentation;

/**
 * Converts a supported PHPDoc tag AST node into SourceSlate's neutral model.
 *
 * Handlers interpret tag semantics only. Tokenization, PHPDoc grammar parsing,
 * and PHPStan type parsing remain shared parser responsibilities.
 */
interface TagHandlerInterface
{
    /**
     * Determines whether this handler owns the normalized tag name.
     *
     * @param non-empty-string $tagName Tag name without the leading at-sign.
     */
    public function supports(string $tagName): bool;

    /**
     * Interprets a parsed tag without mutating the vendor AST.
     *
     * @param non-empty-string $tagName Tag name without the leading at-sign.
     */
    public function handle(string $tagName, PhpDocTagNode $tag): TagDocumentation;
}
