<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Parser;

use PHPUnit\Framework\TestCase;
use SourceSlate\Configuration\Configuration;
use SourceSlate\Parser\PhpSourceParser;

final class PhpSourceParserAttributesTest extends TestCase
{
    public function testPreservesAttributesAndArgumentsAcrossSymbols(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-attributes-' . bin2hex(random_bytes(5));
        mkdir($root . DIRECTORY_SEPARATOR . 'src', 0777, true);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php', <<<'PHP'
<?php
namespace Demo;

#[Entity('example', table: 'examples')]
final class Example
{
    #[Column('string')]
    public string $name;

    #[Marker]
    public const VERSION = '1';

    #[Route('/run', methods: ['POST'])]
    public function run(#[SensitiveParameter] string $secret): void {}
}

#[FunctionMarker(enabled: true)]
function helper(): void {}

enum State
{
    #[CaseMarker('ready')]
    case Ready;
}
PHP);

        try {
            $project = (new PhpSourceParser())->parse($root, new Configuration('Demo', ['src'], [], 'docs'));
            $file = $project->files[0];
            $type = $file->types[0];

            self::assertSame("#[Entity('example', table: 'examples')]", $type->attributes[0]->signature());
            self::assertSame("#[Column('string')]", $type->properties[0]->attributes[0]->signature());
            self::assertSame('#[Marker]', $type->constants[0]->attributes[0]->signature());
            self::assertSame("#[Route('/run', methods: ['POST'])]", $type->methods[0]->attributes[0]->signature());
            self::assertStringStartsWith('#[SensitiveParameter] string $secret', $type->methods[0]->parameters[0]);
            self::assertSame('#[FunctionMarker(enabled: true)]', $file->functions[0]->attributes[0]->signature());
            self::assertSame("#[CaseMarker('ready')]", $file->types[1]->enumCases[0]->attributes[0]->signature());
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
