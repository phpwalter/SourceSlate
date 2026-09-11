<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Source;

use PHPUnit\Framework\TestCase;
use SourceSlate\Source\SourceRequest;

final class SourceRequestTest extends TestCase
{
    public function testBranchIsNormalizedToHeadRef(): void
    {
        $request = new SourceRequest('https://example.test/repo.git', branch: 'develop');

        self::assertSame('refs/heads/develop', $request->requestedRef());
    }

    public function testTagIsNormalizedToTagRef(): void
    {
        $request = new SourceRequest('https://example.test/repo.git', tag: 'v1.2.3');

        self::assertSame('refs/tags/v1.2.3', $request->requestedRef());
    }

    public function testExplicitRefIsPreserved(): void
    {
        $request = new SourceRequest('https://example.test/repo.git', ref: 'v1.2.3');

        self::assertSame('v1.2.3', $request->requestedRef());
    }

    public function testBranchTagAndRefAreMutuallyExclusive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('--branch, --tag, and --ref are mutually exclusive.');

        new SourceRequest('https://example.test/repo.git', branch: 'main', tag: 'v1.0.0');
    }

    public function testSourceTypeAcceptsLocalAndGit(): void
    {
        $local = new SourceRequest('.', sourceType: 'local');
        $git = new SourceRequest('https://example.test/repo.git', sourceType: 'git');

        self::assertSame('local', $local->sourceType);
        self::assertSame('git', $git->sourceType);
    }

    public function testInvalidSourceTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('--source-type must be either local or git.');

        new SourceRequest('.', sourceType: 'archive');
    }

    public function testGitTimeoutMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('--git-timeout must be greater than zero.');

        new SourceRequest('.', gitTimeout: 0);
    }
}
