<?php

declare(strict_types=1);

use SourceSlate\Version;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$json = in_array('--json', $argv, true);
$checks = [];

$add = static function (string $name, bool $passed, string $detail) use (&$checks): void {
    $checks[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
};

$add('version', Version::VERSION === '1.0.0', Version::VERSION);

$composerPath = $root . DIRECTORY_SEPARATOR . 'composer.json';
$composer = json_decode((string) file_get_contents($composerPath), true);
$add('composer-name', is_array($composer) && ($composer['name'] ?? null) === 'phpwalter/sourceslate', (string) ($composer['name'] ?? 'missing'));
$bins = is_array($composer['bin'] ?? null) ? $composer['bin'] : [];
$add('composer-bin', in_array('bin/sourceslate', $bins, true), implode(', ', $bins));
$add('composer-check-script', isset($composer['scripts']['check']), isset($composer['scripts']['check']) ? 'present' : 'missing');

$requiredFiles = [
    'CHANGELOG.md',
    'CONTRIBUTING.md',
    'docs/ARCHITECTURE.md',
    'docs/guides/installation.md',
    'docs/guides/ci.md',
    'docs/guides/remote-repositories.md',
    'docs/guides/troubleshooting.md',
    'docs/guides/security.md',
    'docs/guides/monorepos.md',
    'docs/reference/configuration.md',
    'docs/reference/phpdoc.md',
    'docs/reference/diagnostics.md',
    'docs/release/process.md',
    'docs/release/1.0-acceptance.md',
    'examples/basic/sourceslate.yaml',
];

foreach ($requiredFiles as $file) {
    $add('file:' . $file, is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file)), $file);
}

$ciPath = $root . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'ci.yml';
$ci = is_file($ciPath) ? (string) file_get_contents($ciPath) : '';
$add('ci-linux', str_contains($ci, 'ubuntu-latest'), 'ubuntu-latest');
$add('ci-windows', str_contains($ci, 'windows-latest'), 'windows-latest');
$add('ci-macos', str_contains($ci, 'macos-latest'), 'macos-latest');
$add('ci-php83', str_contains($ci, "'8.3'"), 'PHP 8.3');
$add('ci-php84', str_contains($ci, "'8.4'"), 'PHP 8.4');
$add('ci-global-install', str_contains($ci, 'global-install-smoke'), 'global-install-smoke');
$add('ci-public-https', str_contains($ci, 'public-https-smoke') && str_contains($ci, 'https://github.com/phpwalter/SourceSlate.git'), 'public HTTPS remote build');
$add('ci-determinism', str_contains($ci, '--check'), 'documentation --check');

$releasePath = $root . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'release.yml';
$release = is_file($releasePath) ? (string) file_get_contents($releasePath) : '';
$add('release-tag-pattern', str_contains($release, "'v*.*.*'"), 'v*.*.*');
$add('release-version-guard', str_contains($release, 'check_release_version.php'), 'check_release_version.php');
$add('release-archive', str_contains($release, 'composer archive'), 'composer archive');

$failures = array_values(array_filter($checks, static fn (array $check): bool => !$check['passed']));
$status = $failures === [] ? 'success' : 'error';
$payload = [
    'status' => $status,
    'version' => Version::VERSION,
    'checks' => $checks,
    'failures' => count($failures),
    'exit_code' => $failures === [] ? 0 : 1,
];

if ($json) {
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
} else {
    foreach ($checks as $check) {
        fwrite(STDOUT, sprintf("%s %-34s %s%s", $check['passed'] ? '[OK]' : '[FAIL]', $check['name'], $check['detail'], PHP_EOL));
    }
    fwrite(STDOUT, sprintf("Release audit: %s (%d failure%s)%s", $status, count($failures), count($failures) === 1 ? '' : 's', PHP_EOL));
}

exit($failures === [] ? 0 : 1);
