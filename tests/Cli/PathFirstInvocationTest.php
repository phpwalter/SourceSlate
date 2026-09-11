<?php

/**
 * @file PathFirstInvocationTest.php
 * @path tests/Cli/PathFirstInvocationTest.php
 * @version 1.1.0
 * @date 2026-09-11
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
    public function testExecutableAddsBuildBeforeUnknownPathArgument(): void
    {
        $script = $this->script();

        self::assertStringContainsString("array_splice(\$arguments, 1, 0, ['build']);", $script);
        self::assertStringContainsString("!str_starts_with(\$firstArgument, '-')", $script);
        self::assertStringContainsString("!in_array(\$firstArgument, \$commands, true)", $script);
    }

    public function testAllRegisteredTopLevelCommandsAreExcludedFromPathFirstRewrite(): void
    {
        $script = $this->script();

        foreach ([
            'build',
            'doctor',
            'list',
            'help',
            'completion',
            'cache:list',
            'cache:info',
            'cache:verify',
            'cache:repair',
            'cache:prune',
            'cache:clear',
        ] as $command) {
            self::assertStringContainsString("'{$command}'", $script, sprintf('Missing CLI command allowlist entry: %s', $command));
        }
    }

    public function testOptionsAreNotRewrittenAsProjectPaths(): void
    {
        $script = $this->script();

        self::assertStringContainsString("&& !str_starts_with(\$firstArgument, '-')", $script);
    }

    private function script(): string
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/bin/sourceslate');
        self::assertIsString($script);

        return $script;
    }
}
