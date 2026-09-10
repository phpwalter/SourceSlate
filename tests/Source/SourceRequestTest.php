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

    public function testExplicitRefIsPreserved(): void
    {
        $request = new SourceRequest('https://example.test/repo.git', ref: 'v1.2.3');

        self::assertSame('v1.2.3', $request->requestedRef());
    }

    public function testBranchAndRefCannotBeCombined(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('--branch and --ref cannot be used together.');

        new SourceRequest('https://example.test/repo.git', branch: 'main', ref: 'v1.0.0');
    }
}
