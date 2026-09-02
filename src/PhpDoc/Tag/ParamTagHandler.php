<?php

/**
 * @file ParamTagHandler.php
 * @path src/PhpDoc/Tag/ParamTagHandler.php
 * @version 1.0.0
 * @date 2026-05-20
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Interprets PHPDoc parameter tags using the shared PHPStan-parsed AST.
 */

declare(strict_types=1);

namespace SourceSlate\PhpDoc\Tag;

use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use SourceSlate\PhpDoc\Model\TagDocumentation;

final class ParamTagHandler implements TagHandlerInterface
{
    public function supports(string $tagName): bool
    {
        return $tagName === 'param';
    }

    public function handle(string $tagName, PhpDocTagNode $tag): TagDocumentation
    {
        $value = $tag->value;
        if (!$value instanceof ParamTagValueNode) {
            return new TagDocumentation($tagName, (string) $value, true);
        }

        return new TagDocumentation(
            $tagName,
            (string) $value,
            true,
            (string) $value->type,
            $value->parameterName,
            trim($value->description) !== '' ? trim($value->description) : null,
        );
    }
}
