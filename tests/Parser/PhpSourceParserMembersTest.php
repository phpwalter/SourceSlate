<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Parser;

use PHPUnit\Framework\TestCase;
use SourceSlate\Configuration\Configuration;
use SourceSlate\Parser\PhpSourceParser;

final class PhpSourceParserMembersTest extends TestCase
{
    public function testExtractsPropertiesConstantsEnumCasesAndFunctions(): void
    {
        $root = sys_get_temp_dir() . '/sourceslate-parser-' . bin2hex(random_bytes(4));
        mkdir($root . '/src', 0777, true);
        file_put_contents($root . '/src/Example.php', <<<'PHP'
<?php
namespace Demo;
function helper(string $value): bool { return true; }
final class Example {
    public const VERSION = '1';
    private readonly string $name;
}
enum State: string { case Ready = 'ready'; }
PHP);

        $config = new Configuration('Demo', ['src'], [], 'docs');
        $project = (new PhpSourceParser())->parse($root, $config);

        self::assertCount(1, $project->files);
        self::assertCount(1, $project->files[0]->functions);
        self::assertSame('Demo\\helper', $project->files[0]->functions[0]->fullyQualifiedName);
        self::assertCount(2, $project->files[0]->types);
        self::assertSame('VERSION', $project->files[0]->types[0]->constants[0]->name);
        self::assertSame('name', $project->files[0]->types[0]->properties[0]->name);
        self::assertSame('Ready', $project->files[0]->types[1]->enumCases[0]->name);
    }
}
