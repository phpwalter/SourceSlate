<?php

/**
 * @file PhpDocParserTest.php
 * @path tests/PhpDoc/PhpDocParserTest.php
 * @version 1.0.0
 * @date 2026-05-20
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Verifies semantic PHPDoc dispatch and lossless preservation of unknown tags.
 */

declare(strict_types=1);

namespace SourceSlate\Tests\PhpDoc;

use PHPUnit\Framework\TestCase;
use SourceSlate\PhpDoc\Parser\PhpDocParser;

final class PhpDocParserTest extends TestCase
{
    public function testParsesKnownTagsAndPreservesUnknownTags(): void
    {
        $doc = <<<'PHPDOC'
/**
 * Resolves a provider by canonical identifier.
 *
 * The identifier is normalized before lookup.
 *
 * @param non-empty-string $providerId Canonical provider identifier.
 * @return list<non-empty-string> Matching provider identifiers.
 * @throws \RuntimeException When lookup cannot complete.
 * @sourceslate docs/classes/Auth/Provider.html
 * @vendor-specific preserve-this-value
 */
PHPDOC;

        $result = (new PhpDocParser())->parse($doc);

        self::assertSame('Resolves a provider by canonical identifier.', $result->summary);
        self::assertCount(5, $result->tags);
        self::assertSame('param', $result->tags[0]->name);
        self::assertSame('non-empty-string', $result->tags[0]->type);
        self::assertSame('$providerId', $result->tags[0]->subject);
        self::assertTrue($result->tags[0]->known);
        self::assertSame('sourceslate', $result->tags[3]->name);
        self::assertSame('docs/classes/Auth/Provider.html', $result->tags[3]->subject);
        self::assertSame('vendor-specific', $result->tags[4]->name);
        self::assertFalse($result->tags[4]->known);
        self::assertSame('preserve-this-value', $result->tags[4]->rawValue);
    }
}
