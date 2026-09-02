<?php

/**
 * @file HtmlRendererTest.php
 * @path tests/Renderer/HtmlRendererTest.php
 * @version 1.0.0
 * @date 2026-09-02
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Verifies generation of interconnected type, namespace, source, and search artifacts.
 */

declare(strict_types=1);

namespace SourceSlate\Tests\Renderer;

use PHPUnit\Framework\TestCase;
use SourceSlate\Model\FileDocumentation;
use SourceSlate\Model\MethodDocumentation;
use SourceSlate\Model\ProjectDocumentation;
use SourceSlate\Model\TypeDocumentation;
use SourceSlate\Renderer\HtmlRenderer;

final class HtmlRendererTest extends TestCase
{
    public function testGeneratesInterconnectedDocumentationSite(): void
    {
        $root = sys_get_temp_dir() . '/sourceslate-render-' . bin2hex(random_bytes(4));
        $type = new TypeDocumentation(
            'Example',
            'Demo\\Example',
            'Demo',
            'class',
            'src/Example.php',
            5,
            [],
            [],
            [],
            [new MethodDocumentation('run', 'public', false, ['string $value'], 'bool', 10)],
        );
        $project = new ProjectDocumentation('Demo Project', [
            new FileDocumentation('src/Example.php', ['Demo\\Example'], null, null, [$type], "<?php\nclass Example {}\n"),
        ]);

        (new HtmlRenderer())->render($project, $root);

        self::assertFileExists($root . '/index.html');
        self::assertFileExists($root . '/classes/Demo/Example.html');
        self::assertFileExists($root . '/namespaces/Demo.html');
        self::assertFileExists($root . '/source/src/Example.php.html');
        self::assertFileExists($root . '/assets/search-index.json');
        self::assertFileExists($root . '/assets/search-index.js');
        self::assertFileExists($root . '/assets/sourceslate.js');

        $index = file_get_contents($root . '/index.html');
        self::assertIsString($index);
        self::assertStringContainsString('Demo\\Example', $index);
    }
}
