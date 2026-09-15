#!/usr/bin/env php
<?php

declare(strict_types=1);

use SourceSlate\Configuration\ConfigurationLoader;
use SourceSlate\Parser\PhpSourceParser;
use SourceSlate\Renderer\HtmlRenderer;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = $argv[1] ?? dirname(__DIR__);
$output = $argv[2] ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-benchmark-' . bin2hex(random_bytes(4));
$configFile = $argv[3] ?? null;

$startedAt = hrtime(true);
$memoryBefore = memory_get_usage(true);

$config = (new ConfigurationLoader())->load($projectRoot, $configFile);
$parsedAt = hrtime(true);
$project = (new PhpSourceParser())->parse($projectRoot, $config);
$parseFinishedAt = hrtime(true);
(new HtmlRenderer())->render($project, $output);
$finishedAt = hrtime(true);

$types = 0;
$functions = 0;
foreach ($project->files as $file) {
    $types += count($file->types);
    $functions += count($file->functions);
}

$milliseconds = static fn (int $from, int $to): float => round(($to - $from) / 1_000_000, 3);

$result = [
    'project' => realpath($projectRoot) ?: $projectRoot,
    'output' => $output,
    'files' => count($project->files),
    'types' => $types,
    'functions' => $functions,
    'timings_ms' => [
        'configuration' => $milliseconds($startedAt, $parsedAt),
        'parse' => $milliseconds($parsedAt, $parseFinishedAt),
        'render' => $milliseconds($parseFinishedAt, $finishedAt),
        'total' => $milliseconds($startedAt, $finishedAt),
    ],
    'memory_bytes' => [
        'baseline' => $memoryBefore,
        'peak' => memory_get_peak_usage(true),
    ],
    'php' => PHP_VERSION,
    'os' => PHP_OS_FAMILY,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
