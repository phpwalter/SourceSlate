<?php

/**
 * @file PhpDocParser.php
 * @path src/PhpDoc/Parser/PhpDocParser.php
 * @version 1.0.0
 * @date 2026-05-20
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Parses PHPDoc once, then delegates tag semantics without duplicating grammar logic.
 */

declare(strict_types=1);

namespace SourceSlate\PhpDoc\Parser;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser as VendorPhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use SourceSlate\PhpDoc\Model\PhpDocBlock;

/**
 * Converts raw PHPDoc into SourceSlate's renderer-neutral documentation model.
 *
 * PHPStan owns lexical, PHPDoc, and type-expression parsing. SourceSlate owns
 * semantic interpretation and presentation. This prevents individual tag
 * handlers from implementing competing parsers for PHPStan-compatible types.
 */
final class PhpDocParser
{
    private Lexer $lexer;

    private VendorPhpDocParser $parser;

    public function __construct(private readonly ?TagDispatcher $dispatcher = null)
    {
        $config = new ParserConfig([]);
        $constExprParser = new ConstExprParser($config);
        $typeParser = new TypeParser($config, $constExprParser);

        $this->lexer = new Lexer($config);
        $this->parser = new VendorPhpDocParser($config, $typeParser, $constExprParser);
    }

    /**
     * Parses one complete PHPDoc comment.
     *
     * Unknown tags are preserved rather than rejected. Invalid PHPDoc grammar
     * remains a parser error and is intentionally not converted into guessed data.
     */
    public function parse(string $rawPhpDoc): PhpDocBlock
    {
        $tokens = new TokenIterator($this->lexer->tokenize($rawPhpDoc));
        $node = $this->parser->parse($tokens);
        $narrative = [];
        $tags = [];
        $dispatcher = $this->dispatcher ?? TagDispatcher::defaults();

        foreach ($node->children as $child) {
            if ($child instanceof PhpDocTextNode) {
                $text = trim($child->text);
                if ($text !== '') {
                    $narrative[] = $text;
                }
                continue;
            }

            if ($child instanceof PhpDocTagNode) {
                $tags[] = $dispatcher->dispatch($child);
            }
        }

        [$summary, $description] = $this->splitNarrative($narrative);

        return new PhpDocBlock($summary, $description, $tags);
    }

    /**
     * @param list<string> $narrative
     * @return array{0: ?string, 1: ?string}
     */
    private function splitNarrative(array $narrative): array
    {
        if ($narrative === []) {
            return [null, null];
        }

        $summary = array_shift($narrative);
        $description = $narrative !== [] ? implode("\n\n", $narrative) : null;

        return [$summary, $description];
    }
}
