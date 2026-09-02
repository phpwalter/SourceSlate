<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Configuration;

use PHPUnit\Framework\TestCase;
use SourceSlate\Configuration\ConfigurationLoader;

final class ConfigurationLoaderTest extends TestCase
{
    public function testLoadsZeroConfigurationDefaults(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-' . bin2hex(random_bytes(6));
        mkdir($root . DIRECTORY_SEPARATOR . 'src', 0777, true);

        try {
            $configuration = (new ConfigurationLoader())->load($root);

            self::assertSame(basename($root), $configuration->projectName);
            self::assertSame(['src'], $configuration->sourcePaths);
            self::assertSame(['vendor'], $configuration->excludePaths);
            self::assertSame('docs', $configuration->outputPath);
            self::assertFalse($configuration->updateSource);
        } finally {
            @rmdir($root . DIRECTORY_SEPARATOR . 'src');
            @rmdir($root);
        }
    }
}
