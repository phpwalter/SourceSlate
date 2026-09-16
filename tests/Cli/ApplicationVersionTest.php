<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Cli;

use PHPUnit\Framework\TestCase;
use SourceSlate\Application;
use SourceSlate\Version;
use Symfony\Component\Console\Tester\ApplicationTester;

final class ApplicationVersionTest extends TestCase
{
    public function testApplicationVersionMatchesCentralVersionConstant(): void
    {
        $application = new Application();
        self::assertSame(Version::VERSION, $application->getVersion());
        self::assertSame('1.0.0', Version::VERSION);
    }

    public function testVersionOptionReportsReleaseVersion(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        $status = $tester->run(['--version' => true]);

        self::assertSame(0, $status);
        self::assertStringContainsString('SourceSlate 1.0.0', $tester->getDisplay(true));
    }
}
