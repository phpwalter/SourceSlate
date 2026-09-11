<?php

declare(strict_types=1);

namespace SourceSlate\Source\Git;

final readonly class GitRepositoryIdentity
{
    public function __construct(
        public string $originalUrl,
        public string $redactedUrl,
        public string $canonicalUrl,
        public string $cacheKey,
    ) {
    }

    public static function fromUrl(string $url): self
    {
        $canonical = self::canonicalize($url);

        return new self(
            originalUrl: $url,
            redactedUrl: self::redact($url),
            canonicalUrl: $canonical,
            cacheKey: hash('sha256', $canonical),
        );
    }

    private static function redact(string $url): string
    {
        $value = trim($url);
        $parts = parse_url($value);
        if ($parts === false || !isset($parts['host']) || !isset($parts['scheme'])) {
            return $value;
        }

        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';

        return sprintf('%s://%s%s%s', $scheme, $host, $port, $path);
    }

    private static function canonicalize(string $url): string
    {
        $value = trim($url);

        if (preg_match('#^[^@\s]+@([^:\s]+):(.+)$#', $value, $matches) === 1) {
            $value = 'ssh://' . strtolower($matches[1]) . '/' . $matches[2];
        }

        $value = preg_replace('#\.git/?$#i', '', $value) ?? $value;
        $value = rtrim($value, '/');

        $parts = parse_url($value);
        if ($parts === false || !isset($parts['host'])) {
            return $value;
        }

        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';

        return $host . $port . $path;
    }
}
