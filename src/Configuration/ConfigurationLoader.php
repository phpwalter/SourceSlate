<?php

/**
 * @file ConfigurationLoader.php
 * @path src/Configuration/ConfigurationLoader.php
 * @version 1.1.0
 * @date 2026-09-11
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Loads SourceSlate YAML configuration with deterministic precedence.
 */

declare(strict_types=1);

namespace SourceSlate\Configuration;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

final class ConfigurationLoader
{
    /**
     * Precedence: explicit CLI file > SOURCESLATE_CONFIG > repository config > user config > defaults.
     */
    public function load(string $projectRoot, ?string $configFile = null): Configuration
    {
        $root = realpath($projectRoot);
        if ($root === false || !is_dir($root)) {
            throw new InvalidArgumentException(sprintf('Project root does not exist: %s', $projectRoot));
        }

        $data = [];

        $userConfig = $this->userConfigPath();
        if ($userConfig !== null && is_file($userConfig)) {
            $data = $this->merge($data, $this->parseFile($userConfig));
        }

        $repositoryConfig = $root . DIRECTORY_SEPARATOR . 'sourceslate.yaml';
        if (is_file($repositoryConfig)) {
            $data = $this->merge($data, $this->parseFile($repositoryConfig));
        }

        $environmentConfig = getenv('SOURCESLATE_CONFIG');
        if (is_string($environmentConfig) && trim($environmentConfig) !== '') {
            if (!is_file($environmentConfig)) {
                throw new InvalidArgumentException(sprintf('SOURCESLATE_CONFIG does not exist: %s', $environmentConfig));
            }
            $data = $this->merge($data, $this->parseFile($environmentConfig));
        }

        if ($configFile !== null) {
            if (!is_file($configFile)) {
                throw new InvalidArgumentException(sprintf('Explicit SourceSlate configuration does not exist: %s', $configFile));
            }
            $data = $this->merge($data, $this->parseFile($configFile));
        }

        $project = is_array($data['project'] ?? null) ? $data['project'] : [];
        $source = is_array($data['source'] ?? null) ? $data['source'] : [];
        $output = is_array($data['output'] ?? null) ? $data['output'] : [];
        $headers = is_array($data['source_headers'] ?? null) ? $data['source_headers'] : [];

        $name = trim((string) ($project['name'] ?? basename($root)));
        if ($name === '') {
            throw new InvalidArgumentException('project.name must not be blank.');
        }

        $paths = $source['paths'] ?? $this->discoverSourcePaths($root);
        if (!is_array($paths) || $paths === []) {
            throw new InvalidArgumentException('source.paths must contain at least one source directory.');
        }

        return new Configuration(
            projectName: $name,
            sourcePaths: array_values(array_map('strval', $paths)),
            excludePaths: array_values(array_map('strval', is_array($source['exclude'] ?? null) ? $source['exclude'] : ['vendor'])),
            outputPath: (string) ($output['path'] ?? 'docs'),
            updateSource: (bool) ($headers['update'] ?? false),
        );
    }

    private function parseFile(string $path): array
    {
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new InvalidArgumentException(sprintf('SourceSlate configuration must contain a YAML mapping: %s', $path));
        }

        return $data;
    }

    private function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !array_is_list($value)) {
                $base[$key] = $this->merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private function userConfigPath(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $appData = getenv('APPDATA');
            return is_string($appData) && $appData !== ''
                ? $appData . DIRECTORY_SEPARATOR . 'SourceSlate' . DIRECTORY_SEPARATOR . 'config.yaml'
                : null;
        }

        $home = getenv('HOME');
        if (!is_string($home) || $home === '') {
            return null;
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            return $home . DIRECTORY_SEPARATOR . 'Library' . DIRECTORY_SEPARATOR . 'Application Support' . DIRECTORY_SEPARATOR . 'SourceSlate' . DIRECTORY_SEPARATOR . 'config.yaml';
        }

        return $home . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'sourceslate' . DIRECTORY_SEPARATOR . 'config.yaml';
    }

    private function discoverSourcePaths(string $root): array
    {
        foreach (['src', 'app', 'lib'] as $candidate) {
            if (is_dir($root . DIRECTORY_SEPARATOR . $candidate)) {
                return [$candidate];
            }
        }

        return ['.'];
    }
}
