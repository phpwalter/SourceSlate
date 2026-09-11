<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Source\Git;

use PHPUnit\Framework\TestCase;
use SourceSlate\Source\Git\GitRef;

final class GitRefTest extends TestCase
{
    public function testBranchNormalizesRevision(): void
    {
        $ref = GitRef::branch(' main ');

        self::assertSame('main', $ref->requested);
        self::assertSame('refs/heads/main', $ref->revision);
        self::assertSame('branch', $ref->kind);
    }

    public function testTagNormalizesRevision(): void
    {
        $ref = GitRef::tag(' v1.2.3 ');

        self::assertSame('v1.2.3', $ref->requested);
        self::assertSame('refs/tags/v1.2.3', $ref->revision);
        self::assertSame('tag', $ref->kind);
    }

    public function testExplicitRefIsPreserved(): void
    {
        $ref = GitRef::explicit(' refs/pull/42/head ');

        self::assertSame('refs/pull/42/head', $ref->requested);
        self::assertSame('refs/pull/42/head', $ref->revision);
        self::assertSame('ref', $ref->kind);
    }

    public function testDefaultBranchUsesBranchSemantics(): void
    {
        $ref = GitRef::defaultBranch('develop');

        self::assertSame('develop', $ref->requested);
        self::assertSame('refs/heads/develop', $ref->revision);
        self::assertSame('branch', $ref->kind);
    }

    public function testEmptyBranchIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Git branch cannot be empty.');

        GitRef::branch('   ');
    }

    public function testEmptyTagIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Git tag cannot be empty.');

        GitRef::tag('   ');
    }

    public function testEmptyExplicitRefIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Git ref cannot be empty.');

        GitRef::explicit('   ');
    }
}
