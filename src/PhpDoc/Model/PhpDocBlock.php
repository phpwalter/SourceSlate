<?php

/**
 * @file PhpDocBlock.php
 * @path src/PhpDoc/Model/PhpDocBlock.php
 * @version 1.0.0
 * @date 2026-05-20
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Defines the renderer-neutral representation of one parsed PHPDoc block.
 */

declare(strict_types=1);

namespace SourceSlate\PhpDoc\Model;

/**
 * Represents narrative PHPDoc text and its ordered tag collection.
 *
 * Tag order is preserved exactly as parsed so renderers can reproduce source
 * ordering when desired. An empty description or tag list is valid.
 */
final readonly class PhpDocBlock
{
    /**
     * @param list<TagDocumentation> $tags Tags in source order.
     */
    public function __construct(
        public ?string $summary,
        public ?string $description,
        public array $tags,
    ) {
    }
}
