<?php

/**
 * @file PhpSourceParser.php
 * @path src/Parser/PhpSourceParser.php
 * @version 1.0.0
 * @date 2026-09-02
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Implements deterministic PHP source discovery, AST extraction, and file-level PHPDoc parsing.
 */

declare(strict_types=1);

namespace SourceSlate\Parser;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SourceSlate\Configuration\Configuration;
use SourceSlate\Model\FileDocumentation;
use SourceSlate\Model\ProjectDocumentation;
use SourceSlate\PhpDoc\Model\PhpDocBlock;
use SourceSlate\PhpDoc\Parser\PhpDocParser;
use SplFileInfo;

/**
 * Parses PHP source files into SourceSlate's renderer-independent model.
 *
 * Source files are discovered recursively, excluded paths are filtered before
 * parsing, and output is sorted by project-relative path for deterministic builds.
 * PHPDoc is parsed through one shared grammar pipeline; tag handlers only add
 * semantic interpretation after PHPStan has parsed the comment.
 */
final class PhpSourceParser implements SourceParserInterface
{
    public function parse(string $projectRoot, Configuration $configuration): ProjectDocumentation
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $phpDocParser = new PhpDocParser();
        $nodeFinder = new NodeFinder();
        $files = [];

        foreach ($configuration->sourcePaths as $sourcePath) {
            $absoluteSource = $this->join($projectRoot, $sourcePath);
            if (!is_dir($absoluteSource)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absoluteSource, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $relativePath = $this->relativePath($projectRoot, $file->getPathname());
                if ($this->isExcluded($relativePath, $configuration->excludePaths)) {
                    continue;
                }

                $code = file_get_contents($file->getPathname());
                if ($code === false) {
                    continue;
                }

                $ast = $parser->parse($code) ?? [];
                $symbols = [];

                /** @var Node\Stmt\ClassLike $classLike */
                foreach ($nodeFinder->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $classLike) {
                    if ($classLike->name === null) {
                        continue;
                    }

                    $symbols[] = $classLike->name->toString();
                }

                sort($symbols, SORT_STRING);
                $phpDoc = $this->firstPhpDoc($ast, $phpDocParser);
                $files[] = new FileDocumentation(
                    $relativePath,
                    $symbols,
                    $phpDoc?->summary,
                    $phpDoc,
                );
            }
        }

        usort(
            $files,
            static fn (FileDocumentation $left, FileDocumentation $right): int => $left->path <=> $right->path,
        );

        return new ProjectDocumentation($configuration->projectName, $files);
    }

    /**
     * Returns the first doc comment attached to a top-level node.
     *
     * A conventional file header immediately preceding `declare(strict_types=1)`
     * is attached by nikic/php-parser to that declaration and therefore becomes
     * the file-level documentation block.
     *
     * @param list<Node\Stmt> $ast
     */
    private function firstPhpDoc(array $ast, PhpDocParser $parser): ?PhpDocBlock
    {
        foreach ($ast as $node) {
            $comment = $node->getDocComment();
            if ($comment !== null) {
                return $parser->parse($comment->getText());
            }
        }

        return null;
    }

    /**
     * @param list<non-empty-string> $excludedPaths
     */
    private function isExcluded(string $relativePath, array $excludedPaths): bool
    {
        $normalized = str_replace('\\', '/', $relativePath);

        foreach ($excludedPaths as $excludedPath) {
            $excluded = trim(str_replace('\\', '/', $excludedPath), '/');
            if ($excluded !== '' && ($normalized === $excluded || str_starts_with($normalized, $excluded . '/'))) {
                return true;
            }
        }

        return false;
    }

    private function join(string $root, string $path): string
    {
        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    private function relativePath(string $root, string $path): string
    {
        $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
        $path = str_replace('\\', '/', $path);

        return ltrim(substr($path, strlen($root)), '/');
    }
}
