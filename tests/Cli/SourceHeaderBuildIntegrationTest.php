<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SourceSlate\Command\BuildCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class SourceHeaderBuildIntegrationTest extends TestCase
{
    public function testUpdateSourceWritesOneIdempotentHeader(): void
    {
        $root = $this->temporaryProject();
        $source = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php';
        $output = $root . DIRECTORY_SEPARATOR . 'docs';

        try {
            $first = new CommandTester(new BuildCommand());
            $status = $first->execute([
                'project' => $root,
                '--output' => $output,
                '--update-source' => true,
                '--json' => true,
            ]);

            self::assertSame(0, $status);
            $payload = json_decode($first->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(1, $payload['source_headers_updated']);
            $updated = (string) file_get_contents($source);
            self::assertSame(1, substr_count($updated, '@sourceslate'));
            self::assertStringContainsString('@sourceslate docs/classes/Acme/Example.html', $updated);

            $second = new CommandTester(new BuildCommand());
            $status = $second->execute([
                'project' => $root,
                '--output' => $output,
                '--update-source' => true,
                '--json' => true,
            ]);
            self::assertSame(0, $status);
            $secondPayload = json_decode($second->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(0, $secondPayload['source_headers_updated']);
            self::assertSame($updated, file_get_contents($source));
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testDryRunReportsHeaderChangeWithoutWritingSource(): void
    {
        $root = $this->temporaryProject();
        $source = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php';
        $before = (string) file_get_contents($source);

        try {
            $tester = new CommandTester(new BuildCommand());
            $status = $tester->execute([
                'project' => $root,
                '--output' => $root . DIRECTORY_SEPARATOR . 'docs',
                '--update-source' => true,
                '--dry-run' => true,
                '--json' => true,
            ]);

            self::assertSame(0, $status);
            $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('dry-run', $payload['mode']);
            self::assertSame(1, $payload['source_headers']['changes']);
            self::assertFalse($payload['source_headers']['write']);
            self::assertSame($before, file_get_contents($source));
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testExternalOutputIsAllowedWithoutSourceMutation(): void
    {
        $root = $this->temporaryProject();
        $external = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-external-docs-' . bin2hex(random_bytes(6));

        try {
            $tester = new CommandTester(new BuildCommand());
            $status = $tester->execute([
                'project' => $root,
                '--output' => $external,
                '--json' => true,
            ]);

            self::assertSame(0, $status);
            self::assertFileExists($external . DIRECTORY_SEPARATOR . 'index.html');
        } finally {
            $this->removeDirectory($external);
            $this->removeDirectory($root);
        }
    }

    public function testUpdateSourceRejectsExternalOutputBeforeWritingHostPath(): void
    {
        $root = $this->temporaryProject();
        $source = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php';
        $before = (string) file_get_contents($source);
        $external = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-private-output-' . bin2hex(random_bytes(6));

        try {
            $tester = new CommandTester(new BuildCommand());
            $status = $tester->execute([
                'project' => $root,
                '--output' => $external,
                '--update-source' => true,
                '--json' => true,
            ]);
            $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(15, $status);
            self::assertSame('SS-SRC-0015', $payload['code']);
            self::assertSame($before, file_get_contents($source));
            self::assertStringNotContainsString(str_replace('\\', '/', $external), str_replace('\\', '/', (string) file_get_contents($source)));
        } finally {
            $this->removeDirectory($external);
            $this->removeDirectory($root);
        }
    }

    private function temporaryProject(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-header-build-' . bin2hex(random_bytes(6));
        mkdir($root . DIRECTORY_SEPARATOR . 'src', 0777, true);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php', <<<'PHP'
<?php

namespace Acme;

/** Example service. */
final class Example
{
    public function run(): void {}
}
PHP);
        return realpath($root) ?: $root;
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
