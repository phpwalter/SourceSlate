<?php

/**
 * @file TagDispatcher.php
 * @path src/PhpDoc/Parser/TagDispatcher.php
 * @version 1.0.0
 * @date 2026-05-20
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Routes parsed PHPDoc tags to semantic handlers with a lossless unknown fallback.
 */

declare(strict_types=1);

namespace SourceSlate\PhpDoc\Parser;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use SourceSlate\PhpDoc\Model\TagDocumentation;
use SourceSlate\PhpDoc\Tag\ParamTagHandler;
use SourceSlate\PhpDoc\Tag\ReturnTagHandler;
use SourceSlate\PhpDoc\Tag\SourceSlateTagHandler;
use SourceSlate\PhpDoc\Tag\TagHandlerInterface;
use SourceSlate\PhpDoc\Tag\ThrowsTagHandler;
use SourceSlate\PhpDoc\Tag\UnknownTagHandler;

/**
 * Selects exactly one semantic handler for each parsed PHPDoc tag.
 *
 * Registered handlers are evaluated in constructor order. The fallback handler
 * always preserves unsupported tags and therefore prevents metadata loss.
 */
final readonly class TagDispatcher
{
    /**
     * @param list<TagHandlerInterface> $handlers Semantic handlers in precedence order.
     */
    public function __construct(
        private array $handlers,
        private TagHandlerInterface $fallback,
    ) {
    }

    public static function defaults(): self
    {
        return new self(
            [
                new ParamTagHandler(),
                new ReturnTagHandler(),
                new ThrowsTagHandler(),
                new SourceSlateTagHandler(),
            ],
            new UnknownTagHandler(),
        );
    }

    public function dispatch(PhpDocTagNode $tag): TagDocumentation
    {
        $tagName = ltrim($tag->name, '@');

        foreach ($this->handlers as $handler) {
            if ($handler->supports($tagName)) {
                return $handler->handle($tagName, $tag);
            }
        }

        return $this->fallback->handle($tagName, $tag);
    }
}
