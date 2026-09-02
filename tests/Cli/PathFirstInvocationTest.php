<?php

/**
 * @file PathFirstInvocationTest.php
 * @path tests/Cli/PathFirstInvocationTest.php
 * @version 1.0.0
 * @date 2026-09-02
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Verifies the executable contract for path-first SourceSlate invocation.
 */

declare(strict_types=1);

namespace SourceSlate\Tests\Cli;

use PHPUnit\Framework\TestCase;

final class PathFirstInvocationTest extends TestCase
{
    public function testExecutableAddsBuildBeforePathArgument(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/bin/sourceslate');

        self::assertIsString($script);
        self::assertStringContainsString("array_splice(\$arguments, 1, 0, ['build']);", $script);
        self::assertStringContainsString("['build', 'list', 'help', 'completion']", $script);
    }
}
