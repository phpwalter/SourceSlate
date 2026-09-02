<?php

/**
 * @file ConfigurationLoader.php
 * @path src/Configuration/ConfigurationLoader.php
 * @version 1.0.0
 * @date 2026-09-02
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Loads SourceSlate YAML configuration and supplies zero-configuration defaults.
 */

declare(strict_types=1);

namespace SourceSlate\Configuration;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads and normalizes SourceSlate configuration for a project root.
 *
 * When no configuration file exists, conservative defaults are derived from
 * the project directory. Configuration loading performs filesystem reads only;
 * it does not mutate project files.
 */
final class ConfigurationLoader
{
    /**
     * Loads configuration for a project root.
     *
     * @param non-empty-string $projectRoot Existing project directory.
     * @param non-empty-string|null $configFile Explicit YAML file, or null to auto-detect sourceslate.yaml.
     *
     * @return Configuration Normalized immutable configuration.
     *
     * @throws InvalidArgumentException When the project root or configuration structure is invalid.
     */
    public function load(string $projectRoot, ?string $configFile = null): Configuration
    {
        $root = realpath($projectRoot);
        if ($root === false || !is_dir($root)) {
            throw new InvalidArgumentException(sprintf('Project root does not exist: %s', $projectRoot));
        }

        $path = $configFile ?? $root . DIRECTORY_SEPARATOR . 'sourceslate.yaml';
        $data = is_file($path) ? Yaml::parseFile($path) : [];
        if (!is_array($data)) {
            throw new InvalidArgumentException('SourceSlate configuration must contain a YAML mapping.');
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

    /**
     * @return non-empty-list<non-empty-string> Relative source roots in deterministic preference order.
     */
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
