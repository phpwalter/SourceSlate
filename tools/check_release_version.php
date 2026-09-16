<?php

declare(strict_types=1);

use SourceSlate\Version;

require dirname(__DIR__) . '/vendor/autoload.php';

$ref = getenv('GITHUB_REF_NAME');
if (!is_string($ref) || $ref === '' || !str_starts_with($ref, 'v')) {
    fwrite(STDOUT, "Release version check skipped: current ref is not a version tag." . PHP_EOL);
    exit(0);
}

$expected = 'v' . Version::VERSION;
if ($ref !== $expected) {
    fwrite(STDERR, sprintf(
        "Release tag mismatch: received %s but SourceSlate code reports %s.%s",
        $ref,
        $expected,
        PHP_EOL,
    ));
    exit(1);
}

fwrite(STDOUT, sprintf("Release tag matches SourceSlate %s.%s", Version::VERSION, PHP_EOL));
