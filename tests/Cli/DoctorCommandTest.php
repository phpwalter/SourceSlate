<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SourceSlate\Command\DoctorCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class DoctorCommandTest extends TestCase
{
    public function testJsonDoctorPassesForHealthyLocalProject(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root . DIRECTORY_SEPARATOR . 'src', 0777, true);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php', "<?php\nfinal class Example {}\n");

        try {
            $tester = new CommandTester(new DoctorCommand());
            $status = $tester->execute(['project' => $root, '--json' => true]);
            $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(0, $status);
            self::assertSame('success', $payload['status']);
            self::assertTrue($this->checkPassed($payload['checks'], 'project-root'));
            self::assertTrue($this->checkPassed($payload['checks'], 'source:src'));
            self::assertTrue($this->checkPassed($payload['checks'], 'output'));
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testMissingProjectRootReturnsStableDiagnostic(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-doctor-missing-' . bin2hex(random_bytes(6));
        $tester = new CommandTester(new DoctorCommand());
        $status = $tester->execute(['project' => $root, '--json' => true]);
        $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $status);
        self::assertSame('error', $payload['status']);
        self::assertFalse($this->checkPassed($payload['checks'], 'project-root'));
        self::assertSame('SS-DOC-1010', $this->checkCode($payload['checks'], 'project-root'));
    }

    public function testMissingConfiguredSourcePathFailsPreflight(): void
    {
        $root = $this->temporaryDirectory();
        file_put_contents($root . DIRECTORY_SEPARATOR . 'sourceslate.yaml', "project:\n  name: Fixture\nsource:\n  paths:\n    - missing-src\noutput:\n  path: docs\n");

        try {
            $tester = new CommandTester(new DoctorCommand());
            $status = $tester->execute(['project' => $root, '--json' => true]);
            $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(1, $status);
            self::assertFalse($this->checkPassed($payload['checks'], 'source:missing-src'));
            self::assertSame('SS-DOC-1012', $this->checkCode($payload['checks'], 'source:missing-src'));
        } finally {
            $this->removeDirectory($root);
        }
    }

    /** @param list<array{name:string,passed:bool,detail:string,code:string}> $checks */
    private function checkPassed(array $checks, string $name): bool
    {
        foreach ($checks as $check) {
            if ($check['name'] === $name) {
                return $check['passed'];
            }
        }
        self::fail('Missing doctor check: ' . $name);
    }

    /** @param list<array{name:string,passed:bool,detail:string,code:string}> $checks */
    private function checkCode(array $checks, string $name): string
    {
        foreach ($checks as $check) {
            if ($check['name'] === $name) {
                return $check['code'];
            }
        }
        self::fail('Missing doctor check: ' . $name);
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-doctor-' . bin2hex(random_bytes(6));
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
