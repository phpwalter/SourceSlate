<?php

declare(strict_types=1);

namespace SourceSlate\Tests\SourceHeader;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SourceSlate\SourceHeader\SourceHeaderUpdater;

final class SourceHeaderUpdaterTest extends TestCase
{
    public function testAddsTagToExistingFileDocblock(): void
    {
        $source = "<?php\n\n/**\n * Existing header.\n */\nfinal class Example {}\n";
        $updated = (new SourceHeaderUpdater())->update($source, 'docs/classes/Example.html');

        self::assertStringContainsString('Existing header.', $updated);
        self::assertStringContainsString('@sourceslate docs/classes/Example.html', $updated);
    }

    public function testAddsNewFileDocblockWhenOneDoesNotExist(): void
    {
        $source = "<?php\n\nfinal class Example {}\n";
        $updated = (new SourceHeaderUpdater())->update($source, 'docs/classes/Example.html');

        self::assertStringStartsWith("<?php\n\n/**\n * @sourceslate docs/classes/Example.html\n */", $updated);
    }

    public function testExistingTagIsReplacedIdempotently(): void
    {
        $source = "<?php\n/**\n * @sourceslate docs/old.html\n */\nfinal class Example {}\n";
        $updater = new SourceHeaderUpdater();
        $once = $updater->update($source, 'docs/new.html');
        $twice = $updater->update($once, 'docs/new.html');

        self::assertSame($once, $twice);
        self::assertSame(1, substr_count($once, '@sourceslate'));
        self::assertStringContainsString('@sourceslate docs/new.html', $once);
    }

    public function testDocumentationPathUsesForwardSlashes(): void
    {
        $source = "<?php\nfinal class Example {}\n";
        $updated = (new SourceHeaderUpdater())->update($source, 'docs\\classes\\Example.html');

        self::assertStringContainsString('@sourceslate docs/classes/Example.html', $updated);
    }

    #[DataProvider('unsafeDocumentationPaths')]
    public function testRejectsNonPortableDocumentationPath(string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SourceHeaderUpdater())->update("<?php\nfinal class Example {}\n", $path);
    }

    /** @return iterable<string,array{0:string}> */
    public static function unsafeDocumentationPaths(): iterable
    {
        yield 'unix absolute' => ['/tmp/docs/Example.html'];
        yield 'windows absolute' => ['C:\\docs\\Example.html'];
        yield 'uri' => ['file:///tmp/docs/Example.html'];
        yield 'parent traversal' => ['../docs/Example.html'];
        yield 'nested traversal' => ['docs/../outside/Example.html'];
    }

    public function testRejectsNonPhpSource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SourceHeaderUpdater())->update('final class Example {}', 'docs/Example.html');
    }
}
