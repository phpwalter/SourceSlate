<?php

declare(strict_types=1);

namespace SourceSlate\Parser;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SourceSlate\Configuration\Configuration;
use SourceSlate\Model\ConstantDocumentation;
use SourceSlate\Model\EnumCaseDocumentation;
use SourceSlate\Model\FileDocumentation;
use SourceSlate\Model\FunctionDocumentation;
use SourceSlate\Model\MethodDocumentation;
use SourceSlate\Model\ProjectDocumentation;
use SourceSlate\Model\PropertyDocumentation;
use SourceSlate\Model\TypeDocumentation;
use SourceSlate\PhpDoc\Model\PhpDocBlock;
use SourceSlate\PhpDoc\Parser\PhpDocParser;
use SplFileInfo;

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

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absoluteSource, RecursiveDirectoryIterator::SKIP_DOTS));

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
                $functions = $this->extractFunctions($ast, $relativePath, $phpDocParser);
                $symbols = array_map(static fn (TypeDocumentation $type): string => $type->fullyQualifiedName, $types);
                foreach ($functions as $function) {
                    $symbols[] = $function->fullyQualifiedName;
                }
                sort($symbols, SORT_STRING);

                $filePhpDoc = $this->firstPhpDoc($ast, $phpDocParser);
                $files[] = new FileDocumentation($relativePath, $symbols, $filePhpDoc?->summary, $filePhpDoc, $types, $code, $functions);
            }
        }

        usort($files, static fn (FileDocumentation $a, FileDocumentation $b): int => $a->path <=> $b->path);

        return new ProjectDocumentation($configuration->projectName, $files);
    }

    /** @param list<Node\Stmt> $ast @return list<TypeDocumentation> */
    private function extractTypes(array $ast, string $sourcePath, PhpDocParser $phpDocParser): array
    {
        $types = [];
        foreach ($ast as $statement) {
            if ($statement instanceof Node\Stmt\Namespace_) {
                $namespace = $statement->name?->toString() ?? '';
                foreach ($statement->stmts as $inner) {
                    if ($inner instanceof Node\Stmt\ClassLike && $inner->name !== null) {
                        $types[] = $this->mapType($inner, $namespace, $sourcePath, $phpDocParser);
                    }
                }
            } elseif ($statement instanceof Node\Stmt\ClassLike && $statement->name !== null) {
                $types[] = $this->mapType($statement, '', $sourcePath, $phpDocParser);
            }
        }

        usort($types, static fn (TypeDocumentation $a, TypeDocumentation $b): int => $a->line <=> $b->line);
        return $types;
    }

    /** @param list<Node\Stmt> $ast @return list<FunctionDocumentation> */
    private function extractFunctions(array $ast, string $sourcePath, PhpDocParser $phpDocParser): array
    {
        $functions = [];
        foreach ($ast as $statement) {
            if ($statement instanceof Node\Stmt\Namespace_) {
                $namespace = $statement->name?->toString() ?? '';
                foreach ($statement->stmts as $inner) {
                    if ($inner instanceof Node\Stmt\Function_) {
                        $functions[] = $this->mapFunction($inner, $namespace, $sourcePath, $phpDocParser);
                    }
                }
            } elseif ($statement instanceof Node\Stmt\Function_) {
                $functions[] = $this->mapFunction($statement, '', $sourcePath, $phpDocParser);
            }
        }
        return $functions;
    }

    private function mapType(Node\Stmt\ClassLike $classLike, string $namespace, string $sourcePath, PhpDocParser $phpDocParser): TypeDocumentation
    {
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
        $methods = [];
        $properties = [];
        $constants = [];
        $enumCases = [];

        foreach ($classLike->stmts as $statement) {
            if ($statement instanceof Node\Stmt\TraitUse) {
                foreach ($statement->traits as $trait) {
                    $traits[] = $trait->toString();
                }
            } elseif ($statement instanceof Node\Stmt\ClassMethod) {
                $methods[] = $this->mapMethod($statement, $phpDocParser);
            } elseif ($statement instanceof Node\Stmt\Property) {
                $visibility = $statement->isPrivate() ? 'private' : ($statement->isProtected() ? 'protected' : 'public');
                foreach ($statement->props as $property) {
                    $properties[] = new PropertyDocumentation(
                        $property->name->toString(),
                        $visibility,
                        $statement->isStatic(),
                        $statement->isReadonly(),
                        $statement->type !== null ? $this->typeToString($statement->type) : null,
                        max(1, $statement->getStartLine()),
                        $this->parseDocComment($statement, $phpDocParser),
                    );
                }
            } elseif ($statement instanceof Node\Stmt\ClassConst) {
                $visibility = $statement->isPrivate() ? 'private' : ($statement->isProtected() ? 'protected' : 'public');
                foreach ($statement->consts as $constant) {
                    $constants[] = new ConstantDocumentation($constant->name->toString(), $visibility, max(1, $statement->getStartLine()), $this->parseDocComment($statement, $phpDocParser));
                }
            } elseif ($statement instanceof Node\Stmt\EnumCase) {
                $value = null;
                if ($statement->expr instanceof Node\Scalar\String_ || $statement->expr instanceof Node\Scalar\Int_) {
                    $value = (string) $statement->expr->value;
                }
                $enumCases[] = new EnumCaseDocumentation($statement->name->toString(), max(1, $statement->getStartLine()), $value, $this->parseDocComment($statement, $phpDocParser));
            }
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
            $properties,
            $constants,
            $enumCases,
        );
    }

    private function mapMethod(Node\Stmt\ClassMethod $method, PhpDocParser $phpDocParser): MethodDocumentation
    {
        return new MethodDocumentation(
            $method->name->toString(),
            $method->isPrivate() ? 'private' : ($method->isProtected() ? 'protected' : 'public'),
            $method->isStatic(),
            $this->mapParameters($method->params),
            $method->returnType !== null ? $this->typeToString($method->returnType) : null,
            max(1, $method->getStartLine()),
            $this->parseDocComment($method, $phpDocParser),
        );
    }

    private function mapFunction(Node\Stmt\Function_ $function, string $namespace, string $sourcePath, PhpDocParser $phpDocParser): FunctionDocumentation
    {
        $name = $function->name->toString();
        return new FunctionDocumentation(
            $name,
            $namespace !== '' ? $namespace . '\\' . $name : $name,
            $namespace,
            $sourcePath,
            $this->mapParameters($function->params),
            $function->returnType !== null ? $this->typeToString($function->returnType) : null,
            max(1, $function->getStartLine()),
            $this->parseDocComment($function, $phpDocParser),
        );
    }

    /** @param list<Node\Param> $params @return list<string> */
    private function mapParameters(array $params): array
    {
        $parameters = [];
        foreach ($params as $parameter) {
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
        return $parameters;
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

    /** @param list<Node\Stmt> $ast */
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

    /** @param list<non-empty-string> $excludedPaths */
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
