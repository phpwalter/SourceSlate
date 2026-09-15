<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SourceSlate\Command\BuildCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class MalformedSourceBuildTest extends TestCase
{
    public function testMalformedSourceFailsWithoutReplacingPublishedDocumentation(): void
    {
        $root = $this->temporaryDirectory();
        $source = $root . DIRECTORY_SEPARATOR . 'src';
        $output = $root . DIRECTORY_SEPARATOR . 'docs';
        mkdir($source, 0777, true);
        mkdir($output, 0777, true);
        file_put_contents($source . DIRECTORY_SEPARATOR . 'Broken.php', "<?php\nfinal class Broken { public function nope( { }\n");
        file_put_contents($output . DIRECTORY_SEPARATOR . 'index.html', 'last-known-good');
        file_put_contents($output . DIRECTORY_SEPARATOR . '.sourceslate-manifest.json', "{}\n");

        try {
            $tester = new CommandTester(new BuildCommand());
            $status = $tester->execute([
                'project' => $root,
                '--output' => $output,
                '--json' => true,
            ]);
            $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

            self::assertNotSame(0, $status);
            self::assertSame('error', $payload['status']);
            self::assertSame('SS-INT-0001', $payload['code']);
            self::assertSame('last-known-good', file_get_contents($output . DIRECTORY_SEPARATOR . 'index.html'));
            self::assertFileExists($output . DIRECTORY_SEPARATOR . '.sourceslate-manifest.json');
            self::assertSame([], glob($root . DIRECTORY_SEPARATOR . '.sourceslate-build-*') ?: []);
            self::assertSame([], glob($output . '.sourceslate-previous-*') ?: []);
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-malformed-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
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
