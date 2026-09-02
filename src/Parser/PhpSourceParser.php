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
 * Implements deterministic PHP discovery and extraction of file, type, method, and PHPDoc metadata.
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
use SourceSlate\Model\MethodDocumentation;
use SourceSlate\Model\ProjectDocumentation;
use SourceSlate\Model\TypeDocumentation;
use SourceSlate\PhpDoc\Model\PhpDocBlock;
use SourceSlate\PhpDoc\Parser\PhpDocParser;
use SplFileInfo;

/**
 * Parses PHP source files into SourceSlate's renderer-independent model.
 *
 * Source discovery is deterministic. Native declarations are extracted from the
 * nikic/php-parser AST while PHPDoc grammar remains delegated to the shared
 * PHPDoc parser and semantic tag handlers.
 */
final class PhpSourceParser implements SourceParserInterface
{
    public function parse(string $projectRoot, Configuration $configuration): ProjectDocumentation
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $phpDocParser = new PhpDocParser();
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
                $types = $this->extractTypes($ast, $relativePath, $phpDocParser);
                $symbols = array_map(
                    static fn (TypeDocumentation $type): string => $type->fullyQualifiedName,
                    $types,
                );
                $filePhpDoc = $this->firstPhpDoc($ast, $phpDocParser);

                $files[] = new FileDocumentation(
                    $relativePath,
                    $symbols,
                    $filePhpDoc?->summary,
                    $filePhpDoc,
                    $types,
                    $code,
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
     * @param list<Node\Stmt> $ast
     * @return list<TypeDocumentation>
     */
    private function extractTypes(array $ast, string $sourcePath, PhpDocParser $phpDocParser): array
    {
        $types = [];

        foreach ($ast as $statement) {
            if ($statement instanceof Node\Stmt\Namespace_) {
                $namespace = $statement->name?->toString() ?? '';
                $types = array_merge(
                    $types,
                    $this->extractTypesFromStatements($statement->stmts, $namespace, $sourcePath, $phpDocParser),
                );
                continue;
            }

            if ($statement instanceof Node\Stmt\ClassLike && $statement->name !== null) {
                $types[] = $this->mapType($statement, '', $sourcePath, $phpDocParser);
            }
        }

        usort(
            $types,
            static fn (TypeDocumentation $left, TypeDocumentation $right): int => $left->line <=> $right->line,
        );

        return $types;
    }

    /**
     * @param list<Node\Stmt> $statements
     * @return list<TypeDocumentation>
     */
    private function extractTypesFromStatements(
        array $statements,
        string $namespace,
        string $sourcePath,
        PhpDocParser $phpDocParser,
    ): array {
        $finder = new NodeFinder();
        $types = [];

        /** @var Node\Stmt\ClassLike $classLike */
        foreach ($finder->findInstanceOf($statements, Node\Stmt\ClassLike::class) as $classLike) {
            if ($classLike->name === null) {
                continue;
            }

            $types[] = $this->mapType($classLike, $namespace, $sourcePath, $phpDocParser);
        }

        return $types;
    }

    private function mapType(
        Node\Stmt\ClassLike $classLike,
        string $namespace,
        string $sourcePath,
        PhpDocParser $phpDocParser,
    ): TypeDocumentation {
        $name = $classLike->name?->toString() ?? '';
        $fullyQualifiedName = $namespace !== '' ? $namespace . '\\' . $name : $name;
        $extends = [];
        $implements = [];

        if ($classLike instanceof Node\Stmt\Class_) {
            if ($classLike->extends !== null) {
                $extends[] = $classLike->extends->toString();
            }
            $implements = array_map(static fn (Node\Name $name): string => $name->toString(), $classLike->implements);
        } elseif ($classLike instanceof Node\Stmt\Interface_) {
            $extends = array_map(static fn (Node\Name $name): string => $name->toString(), $classLike->extends);
        } elseif ($classLike instanceof Node\Stmt\Enum_) {
            $implements = array_map(static fn (Node\Name $name): string => $name->toString(), $classLike->implements);
        }

        $traits = [];
        foreach ($classLike->stmts as $statement) {
            if (!$statement instanceof Node\Stmt\TraitUse) {
                continue;
            }

            foreach ($statement->traits as $trait) {
                $traits[] = $trait->toString();
            }
        }

        $methods = [];
        foreach ($classLike->getMethods() as $method) {
            $methods[] = $this->mapMethod($method, $phpDocParser);
        }

        return new TypeDocumentation(
            $name,
            $fullyQualifiedName,
            $namespace,
            $this->typeKind($classLike),
            $sourcePath,
            max(1, $classLike->getStartLine()),
            $extends,
            $implements,
            $traits,
            $methods,
            $this->parseDocComment($classLike, $phpDocParser),
        );
    }

    private function mapMethod(Node\Stmt\ClassMethod $method, PhpDocParser $phpDocParser): MethodDocumentation
    {
        $parameters = [];
        foreach ($method->params as $parameter) {
            $text = '';
            if ($parameter->type !== null) {
                $text .= $this->typeToString($parameter->type) . ' ';
            }
            if ($parameter->byRef) {
                $text .= '&';
            }
            if ($parameter->variadic) {
                $text .= '...';
            }
            $text .= '$' . (is_string($parameter->var->name) ? $parameter->var->name : 'parameter');
            $parameters[] = $text;
        }

        $visibility = $method->isPrivate() ? 'private' : ($method->isProtected() ? 'protected' : 'public');

        return new MethodDocumentation(
            $method->name->toString(),
            $visibility,
            $method->isStatic(),
            $parameters,
            $method->returnType !== null ? $this->typeToString($method->returnType) : null,
            max(1, $method->getStartLine()),
            $this->parseDocComment($method, $phpDocParser),
        );
    }

    private function typeToString(Node $type): string
    {
        if ($type instanceof Node\Identifier || $type instanceof Node\Name) {
            return $type->toString();
        }

        if ($type instanceof Node\NullableType) {
            return '?' . $this->typeToString($type->type);
        }

        if ($type instanceof Node\UnionType) {
            return implode('|', array_map(fn (Node $inner): string => $this->typeToString($inner), $type->types));
        }

        if ($type instanceof Node\IntersectionType) {
            return implode('&', array_map(fn (Node $inner): string => $this->typeToString($inner), $type->types));
        }

        return (string) $type;
    }

    /**
     * @return 'class'|'interface'|'trait'|'enum'
     */
    private function typeKind(Node\Stmt\ClassLike $classLike): string
    {
        return match (true) {
            $classLike instanceof Node\Stmt\Interface_ => 'interface',
            $classLike instanceof Node\Stmt\Trait_ => 'trait',
            $classLike instanceof Node\Stmt\Enum_ => 'enum',
            default => 'class',
        };
    }

    private function parseDocComment(Node $node, PhpDocParser $parser): ?PhpDocBlock
    {
        $comment = $node->getDocComment();

        return $comment !== null ? $parser->parse($comment->getText()) : null;
    }

    /**
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
