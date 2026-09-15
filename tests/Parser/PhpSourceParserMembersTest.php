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
function helper(string $value = 'x'): bool { return true; }
final class Example {
    public const VERSION = '1';
    private readonly string $name;
    public function __construct(public readonly int $id = 7) {}
}
enum State: string { case Ready = 'ready'; }
PHP);

        try {
            $config = new Configuration('Demo', ['src'], [], 'docs');
            $project = (new PhpSourceParser())->parse($root, $config);

            self::assertCount(1, $project->files);
            self::assertCount(1, $project->files[0]->functions);
            self::assertSame('Demo\\helper', $project->files[0]->functions[0]->fullyQualifiedName);
            self::assertSame("string \$value = 'x'", $project->files[0]->functions[0]->parameters[0]);

            self::assertCount(2, $project->files[0]->types);
            $example = $project->files[0]->types[0];
            self::assertSame('VERSION', $example->constants[0]->name);
            self::assertSame('name', $example->properties[0]->name);
            self::assertSame('id', $example->properties[1]->name);
            self::assertSame('public', $example->properties[1]->visibility);
            self::assertTrue($example->properties[1]->readonly);
            self::assertSame('int', $example->properties[1]->type);
            self::assertSame('public readonly int $id = 7', $example->methods[0]->parameters[0]);
            self::assertSame('Ready', $project->files[0]->types[1]->enumCases[0]->name);
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $child = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($child) && !is_link($child)) {
                $this->removeDirectory($child);
            } else {
                @unlink($child);
            }
        }
        @rmdir($path);
    }
}
