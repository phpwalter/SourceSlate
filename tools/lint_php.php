<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$targets = ['src', 'tests', 'bin', 'tools', 'examples'];
$files = [];

foreach ($targets as $target) {
    $path = $root . DIRECTORY_SEPARATOR . $target;
    if (is_file($path) && str_ends_with(strtolower($path), '.php')) {
        $files[] = $path;
        continue;
    }
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files, SORT_STRING);
$failures = [];

foreach ($files as $file) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
        $failures[] = [
            'file' => $relative,
            'message' => trim(implode(PHP_EOL, $output)),
        ];
    }
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("PHP syntax validation failed for %d file(s).%s", count($failures), PHP_EOL));
    foreach ($failures as $failure) {
        fwrite(STDERR, sprintf("- %s: %s%s", $failure['file'], $failure['message'], PHP_EOL));
    }
    exit(1);
}

fwrite(STDOUT, sprintf("PHP syntax OK: %d file(s).%s", count($files), PHP_EOL));
