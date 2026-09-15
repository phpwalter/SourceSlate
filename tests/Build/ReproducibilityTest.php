<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Build;

use PHPUnit\Framework\TestCase;
use SourceSlate\Build\DocumentationComparator;
use SourceSlate\Configuration\Configuration;
use SourceSlate\Parser\PhpSourceParser;
use SourceSlate\Renderer\HtmlRenderer;

final class ReproducibilityTest extends TestCase
{
    public function testIdenticalInputProducesIdenticalDocumentationTrees(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-repro-' . bin2hex(random_bytes(6));
        $source = $root . DIRECTORY_SEPARATOR . 'src';
        $first = $root . DIRECTORY_SEPARATOR . 'docs-first';
        $second = $root . DIRECTORY_SEPARATOR . 'docs-second';
        mkdir($source, 0777, true);
        file_put_contents($source . DIRECTORY_SEPARATOR . 'Example.php', <<<'PHP'
<?php

namespace Acme;

/** Example service. */
final class Example
{
    public const VERSION = 1;

    /** Run the example. */
    public function run(string $value): string
    {
        return $value;
    }
}
PHP);

        try {
            $configuration = new Configuration(
                projectName: 'Fixture',
                sourcePaths: ['src'],
                excludePaths: ['vendor'],
                outputPath: 'docs',
            );
            $project = (new PhpSourceParser())->parse($root, $configuration);
            $renderer = new HtmlRenderer();
            $renderer->render($project, $first);
            $renderer->render($project, $second);

            $comparison = (new DocumentationComparator())->compare($first, $second);
            self::assertTrue($comparison['matches'], json_encode($comparison, JSON_PRETTY_PRINT));
            self::assertSame($this->treeDigest($first), $this->treeDigest($second));
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function treeDigest(string $root): string
    {
        $entries = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $relative = substr($item->getPathname(), strlen($root) + 1);
            $entries[str_replace(DIRECTORY_SEPARATOR, '/', $relative)] = hash_file('sha256', $item->getPathname());
        }
        ksort($entries, SORT_STRING);
        return hash('sha256', json_encode($entries, JSON_THROW_ON_ERROR));
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
