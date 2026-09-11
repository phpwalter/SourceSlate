<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Configuration;

use PHPUnit\Framework\TestCase;
use SourceSlate\Configuration\ConfigurationLoader;

final class ConfigurationLoaderTest extends TestCase
{
    public function testLoadsZeroConfigurationDefaults(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root . DIRECTORY_SEPARATOR . 'src', 0777, true);

        try {
            $configuration = (new ConfigurationLoader())->load($root);

            self::assertSame(basename($root), $configuration->projectName);
            self::assertSame(['src'], $configuration->sourcePaths);
            self::assertSame(['vendor'], $configuration->excludePaths);
            self::assertSame('docs', $configuration->outputPath);
            self::assertFalse($configuration->updateSource);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testSourcePathConfigurationOverridesRepositoryRootConfiguration(): void
    {
        $repository = $this->temporaryDirectory();
        $source = $repository . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'example';
        mkdir($source . DIRECTORY_SEPARATOR . 'src', 0777, true);
        mkdir($repository . DIRECTORY_SEPARATOR . '.git');

        file_put_contents(
            $repository . DIRECTORY_SEPARATOR . 'sourceslate.yaml',
            "project:\n  name: Repository Name\nsource:\n  exclude: [vendor, generated]\noutput:\n  path: repository-docs\n",
        );
        file_put_contents(
            $source . DIRECTORY_SEPARATOR . 'sourceslate.yaml',
            "project:\n  name: Package Name\noutput:\n  path: package-docs\n",
        );

        try {
            $configuration = (new ConfigurationLoader())->load($source);

            self::assertSame('Package Name', $configuration->projectName);
            self::assertSame(['src'], $configuration->sourcePaths);
            self::assertSame(['vendor', 'generated'], $configuration->excludePaths);
            self::assertSame('package-docs', $configuration->outputPath);
        } finally {
            $this->removeDirectory($repository);
        }
    }

    public function testEnvironmentConfigurationOverridesRepositoryConfiguration(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root . DIRECTORY_SEPARATOR . 'src', 0777, true);
        $environmentConfig = $root . DIRECTORY_SEPARATOR . 'environment.yaml';
        $previous = getenv('SOURCESLATE_CONFIG');

        file_put_contents(
            $root . DIRECTORY_SEPARATOR . 'sourceslate.yaml',
            "project:\n  name: Repository Name\noutput:\n  path: repository-docs\n",
        );
        file_put_contents(
            $environmentConfig,
            "project:\n  name: Environment Name\noutput:\n  path: environment-docs\n",
        );
        putenv('SOURCESLATE_CONFIG=' . $environmentConfig);

        try {
            $configuration = (new ConfigurationLoader())->load($root);

            self::assertSame('Environment Name', $configuration->projectName);
            self::assertSame('environment-docs', $configuration->outputPath);
        } finally {
            $this->restoreEnvironment('SOURCESLATE_CONFIG', $previous);
            $this->removeDirectory($root);
        }
    }

    public function testExplicitConfigurationOverridesEnvironmentConfiguration(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root . DIRECTORY_SEPARATOR . 'src', 0777, true);
        $environmentConfig = $root . DIRECTORY_SEPARATOR . 'environment.yaml';
        $explicitConfig = $root . DIRECTORY_SEPARATOR . 'explicit.yaml';
        $previous = getenv('SOURCESLATE_CONFIG');

        file_put_contents($environmentConfig, "project:\n  name: Environment Name\n");
        file_put_contents(
            $explicitConfig,
            "project:\n  name: Explicit Name\nsource_headers:\n  update: true\n",
        );
        putenv('SOURCESLATE_CONFIG=' . $environmentConfig);

        try {
            $configuration = (new ConfigurationLoader())->load($root, $explicitConfig);

            self::assertSame('Explicit Name', $configuration->projectName);
            self::assertTrue($configuration->updateSource);
        } finally {
            $this->restoreEnvironment('SOURCESLATE_CONFIG', $previous);
            $this->removeDirectory($root);
        }
    }

    public function testRepositoryConfigurationRejectsSecuritySensitiveSettings(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root . DIRECTORY_SEPARATOR . 'src', 0777, true);
        file_put_contents(
            $root . DIRECTORY_SEPARATOR . 'sourceslate.yaml',
            "security:\n  allow_insecure_transport: true\n",
        );

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Repository configuration may not define security-sensitive "security" settings');

            (new ConfigurationLoader())->load($root);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testExplicitConfigurationMayContainSecuritySettingsWithoutRepositoryGuard(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root . DIRECTORY_SEPARATOR . 'src', 0777, true);
        $explicitConfig = $root . DIRECTORY_SEPARATOR . 'explicit.yaml';
        file_put_contents(
            $explicitConfig,
            "security:\n  allow_insecure_transport: false\nproject:\n  name: Explicit Name\n",
        );

        try {
            $configuration = (new ConfigurationLoader())->load($root, $explicitConfig);
            self::assertSame('Explicit Name', $configuration->projectName);
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);

        return $root;
    }

    private function restoreEnvironment(string $name, string|false $value): void
    {
        if ($value === false) {
            putenv($name);
            return;
        }

        putenv($name . '=' . $value);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

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
