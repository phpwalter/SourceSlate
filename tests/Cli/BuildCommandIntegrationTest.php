<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SourceSlate\Command\BuildCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class BuildCommandIntegrationTest extends TestCase
{
    public function testLocalBuildEmitsSuccessfulJsonContract(): void
    {
        $root = $this->temporaryProject();
        $output = $root . DIRECTORY_SEPARATOR . 'generated-docs';

        try {
            $tester = new CommandTester(new BuildCommand());
            $exitCode = $tester->execute([
                'project' => $root,
                '--output' => $output,
                '--json' => true,
            ]);

            self::assertSame(0, $exitCode);
            $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('success', $payload['status']);
            self::assertSame('local', $payload['source']['type']);
            self::assertSame(1, $payload['documentation']['files_scanned']);
            self::assertSame($output, $payload['documentation']['output']);
            self::assertFileExists($output . DIRECTORY_SEPARATOR . 'index.html');
            self::assertFileExists($output . DIRECTORY_SEPARATOR . '.sourceslate-manifest.json');
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testCheckSucceedsWhenPublishedDocumentationMatches(): void
    {
        $root = $this->temporaryProject();
        $output = $root . DIRECTORY_SEPARATOR . 'generated-docs';

        try {
            $build = new CommandTester(new BuildCommand());
            self::assertSame(0, $build->execute(['project' => $root, '--output' => $output]));

            $check = new CommandTester(new BuildCommand());
            $exitCode = $check->execute([
                'project' => $root,
                '--output' => $output,
                '--check' => true,
                '--json' => true,
            ]);

            self::assertSame(0, $exitCode);
            $payload = json_decode($check->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('success', $payload['status']);
            self::assertSame('check', $payload['mode']);
            self::assertTrue($payload['documentation']['up_to_date']);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testCheckReturnsStableDiagnosticWhenPublishedDocumentationIsStale(): void
    {
        $root = $this->temporaryProject();
        $output = $root . DIRECTORY_SEPARATOR . 'generated-docs';

        try {
            $build = new CommandTester(new BuildCommand());
            self::assertSame(0, $build->execute(['project' => $root, '--output' => $output]));
            file_put_contents($output . DIRECTORY_SEPARATOR . 'index.html', 'stale documentation');

            $check = new CommandTester(new BuildCommand());
            $exitCode = $check->execute([
                'project' => $root,
                '--output' => $output,
                '--check' => true,
                '--json' => true,
            ]);

            self::assertSame(50, $exitCode);
            $payload = json_decode($check->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('error', $payload['status']);
            self::assertSame('SS-DOC-0050', $payload['code']);
            self::assertSame(50, $payload['exit_code']);
            self::assertContains('index.html', $payload['differences']['changed']);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testRemoteGitSourceRequiresExplicitOutputBeforeNetworkAccess(): void
    {
        $tester = new CommandTester(new BuildCommand());
        $exitCode = $tester->execute([
            'project' => 'https://example.invalid/acme/repository.git',
            '--source-type' => 'git',
            '--json' => true,
        ]);

        self::assertSame(40, $exitCode);
        $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('error', $payload['status']);
        self::assertSame('SS-OUT-0040', $payload['code']);
        self::assertSame(40, $payload['exit_code']);
        self::assertStringContainsString('--output is required for remote Git sources', $payload['message']);
    }

    public function testUnmanagedOutputDirectoryReturnsStableOutputSafetyDiagnostic(): void
    {
        $root = $this->temporaryProject();
        $output = $root . DIRECTORY_SEPARATOR . 'generated-docs';
        mkdir($output);
        file_put_contents($output . DIRECTORY_SEPARATOR . 'keep.txt', 'do not replace');

        try {
            $tester = new CommandTester(new BuildCommand());
            $exitCode = $tester->execute([
                'project' => $root,
                '--output' => $output,
                '--json' => true,
            ]);

            self::assertSame(41, $exitCode);
            $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('SS-OUT-0041', $payload['code']);
            self::assertSame(41, $payload['exit_code']);
            self::assertFileExists($output . DIRECTORY_SEPARATOR . 'keep.txt');
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function temporaryProject(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-cli-' . bin2hex(random_bytes(6));
        mkdir($root . DIRECTORY_SEPARATOR . 'src', 0777, true);
        file_put_contents(
            $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php',
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace Example;\n\n/** Example service. */\nfinal class Service\n{\n    /** Return a greeting. */\n    public function greet(): string\n    {\n        return 'hello';\n    }\n}\n",
        );

        return $root;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child) && !is_link($child)) {
                $this->removeDirectory($child);
            } else {
                @unlink($child);
            }
        }
        @rmdir($path);
    }
}
