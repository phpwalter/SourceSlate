<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Renderer;

use PHPUnit\Framework\TestCase;
use SourceSlate\Model\AttributeDocumentation;
use SourceSlate\Model\FileDocumentation;
use SourceSlate\Model\ProjectDocumentation;
use SourceSlate\Model\TypeDocumentation;
use SourceSlate\PhpDoc\Model\PhpDocBlock;
use SourceSlate\PhpDoc\Model\TagDocumentation;
use SourceSlate\Renderer\HtmlRenderer;

final class HtmlRendererLinksTest extends TestCase
{
    public function testRendersAttributesRelationshipsAndResolvableSeeLinks(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-links-' . bin2hex(random_bytes(5));

        $base = new TypeDocumentation('Base', 'Demo\\Base', 'Demo', 'class', 'src/Base.php', 3, [], [], [], []);
        $contract = new TypeDocumentation('Contract', 'Demo\\Contract', 'Demo', 'interface', 'src/Contract.php', 3, [], [], [], []);
        $trait = new TypeDocumentation('HelperTrait', 'Demo\\HelperTrait', 'Demo', 'trait', 'src/HelperTrait.php', 3, [], [], [], []);
        $child = new TypeDocumentation(
            'Child',
            'Demo\\Child',
            'Demo',
            'class',
            'src/Child.php',
            8,
            ['Base'],
            ['Contract'],
            ['HelperTrait'],
            [],
            new PhpDocBlock('Child implementation.', null, [
                new TagDocumentation('see', 'Base', true, description: 'Base'),
            ]),
            attributes: [new AttributeDocumentation('Service', ["'primary'"])],
        );

        $project = new ProjectDocumentation('Links', [
            new FileDocumentation('src/Base.php', ['Demo\\Base'], null, null, [$base], '<?php'),
            new FileDocumentation('src/Contract.php', ['Demo\\Contract'], null, null, [$contract], '<?php'),
            new FileDocumentation('src/HelperTrait.php', ['Demo\\HelperTrait'], null, null, [$trait], '<?php'),
            new FileDocumentation('src/Child.php', ['Demo\\Child'], null, null, [$child], '<?php'),
        ]);

        try {
            (new HtmlRenderer())->render($project, $root);
            $html = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Demo' . DIRECTORY_SEPARATOR . 'Child.html');

            self::assertStringContainsString('#[Service(&#039;primary&#039;)]', $html);
            self::assertStringContainsString('href="../../classes/Demo/Base.html"', $html);
            self::assertStringContainsString('href="../../interfaces/Demo/Contract.html"', $html);
            self::assertStringContainsString('href="../../traits/Demo/HelperTrait.html"', $html);
            self::assertGreaterThanOrEqual(2, substr_count($html, '../../classes/Demo/Base.html'));
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
