<?php

/**
 * @file TagDocumentation.php
 * @path src/PhpDoc/Model/TagDocumentation.php
 * @version 1.0.0
 * @date 2026-05-20
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Represents one normalized PHPDoc tag without coupling renderers to parser internals.
 */

declare(strict_types=1);

namespace SourceSlate\PhpDoc\Model;

/**
 * Carries the semantic fields SourceSlate can prove for a PHPDoc tag.
 *
 * Unknown tags remain lossless through name and raw value preservation. Known
 * handlers may additionally populate type, subject, and description fields.
 */
final readonly class TagDocumentation
{
    public function __construct(
        public string $name,
        public string $rawValue,
        public bool $known,
        public ?string $type = null,
        public ?string $subject = null,
        public ?string $description = null,
    ) {
    }
}
