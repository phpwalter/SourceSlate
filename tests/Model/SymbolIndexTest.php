<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Model;

use PHPUnit\Framework\TestCase;
use SourceSlate\Model\FileDocumentation;
use SourceSlate\Model\MethodDocumentation;
use SourceSlate\Model\ProjectDocumentation;
use SourceSlate\Model\SymbolIndex;
use SourceSlate\Model\TypeDocumentation;

final class SymbolIndexTest extends TestCase
{
    public function testResolvesQualifiedAndSameNamespaceTypeNames(): void
    {
        $type = new TypeDocumentation(
            name: 'Example',
            fullyQualifiedName: 'Acme\\Domain\\Example',
            namespace: 'Acme\\Domain',
            kind: 'class',
            sourcePath: 'src/Example.php',
            line: 10,
            extends: [],
            implements: [],
            traits: [],
            methods: [],
        );
        $index = SymbolIndex::fromProject(new ProjectDocumentation('Fixture', [
            new FileDocumentation(
                path: 'src/Example.php',
                declaredSymbols: ['Acme\\Domain\\Example'],
                types: [$type],
            ),
        ]));

        self::assertSame('Acme\\Domain\\Example', $index->resolve('Acme\\Domain\\Example')?->qualifiedName);
        self::assertSame('Acme\\Domain\\Example', $index->resolve('Example', 'Acme\\Domain')?->qualifiedName);
    }

    public function testResolvesMethodWithAndWithoutCallSuffix(): void
    {
        $method = new MethodDocumentation('run', 'public', false, [], 'void', 20);
        $type = new TypeDocumentation('Example', 'Acme\\Example', 'Acme', 'class', 'src/Example.php', 10, [], [], [], [$method]);
        $index = SymbolIndex::fromProject(new ProjectDocumentation('Fixture', [
            new FileDocumentation(
                path: 'src/Example.php',
                declaredSymbols: ['Acme\\Example'],
                types: [$type],
            ),
        ]));

        self::assertSame('method', $index->resolve('Acme\\Example::run()')?->kind);
        self::assertSame('method', $index->resolve('Acme\\Example::run')?->kind);
    }

    public function testAmbiguousShortNameDoesNotGuess(): void
    {
        $first = new TypeDocumentation('Example', 'One\\Example', 'One', 'class', 'one.php', 1, [], [], [], []);
        $second = new TypeDocumentation('Example', 'Two\\Example', 'Two', 'class', 'two.php', 1, [], [], [], []);
        $index = SymbolIndex::fromProject(new ProjectDocumentation('Fixture', [
            new FileDocumentation(path: 'one.php', declaredSymbols: ['One\\Example'], types: [$first]),
            new FileDocumentation(path: 'two.php', declaredSymbols: ['Two\\Example'], types: [$second]),
        ]));

        self::assertNull($index->resolve('Example'));
        self::assertSame('One\\Example', $index->resolve('Example', 'One')?->qualifiedName);
    }
}
