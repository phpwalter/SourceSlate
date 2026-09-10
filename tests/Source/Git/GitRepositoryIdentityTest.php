<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Source\Git;

use PHPUnit\Framework\TestCase;
use SourceSlate\Source\Git\GitRepositoryIdentity;

final class GitRepositoryIdentityTest extends TestCase
{
    public function testHttpsGitSuffixIsNormalized(): void
    {
        $identity = GitRepositoryIdentity::fromUrl('https://github.com/phpwalter/SourceSlate.git');

        self::assertSame('github.com/phpwalter/SourceSlate', $identity->canonicalUrl);
    }

    public function testSshAndHttpsFormsShareCanonicalIdentity(): void
    {
        $https = GitRepositoryIdentity::fromUrl('https://github.com/phpwalter/SourceSlate.git');
        $ssh = GitRepositoryIdentity::fromUrl('git@github.com:phpwalter/SourceSlate.git');

        self::assertSame($https->canonicalUrl, $ssh->canonicalUrl);
        self::assertSame($https->cacheKey, $ssh->cacheKey);
    }

    public function testDifferentHostsDoNotCollide(): void
    {
        $github = GitRepositoryIdentity::fromUrl('https://github.com/acme/api.git');
        $gitlab = GitRepositoryIdentity::fromUrl('https://gitlab.com/acme/api.git');

        self::assertNotSame($github->cacheKey, $gitlab->cacheKey);
    }
}
