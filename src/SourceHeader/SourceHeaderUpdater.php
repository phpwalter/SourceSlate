<?php

declare(strict_types=1);

namespace SourceSlate\SourceHeader;

final class SourceHeaderUpdater
{
    public function update(string $source, string $documentationPath): string
    {
        $documentationPath = trim(str_replace('\\', '/', $documentationPath));
        $this->assertPortableDocumentationPath($documentationPath);

        $tagLine = '@sourceslate ' . $documentationPath;

        if (preg_match('/@sourceslate\s+[^\r\n*]+/', $source) === 1) {
            return preg_replace('/@sourceslate\s+[^\r\n*]+/', $tagLine, $source, 1) ?? $source;
        }

        if (preg_match('/<\?php\s*\R\s*(\/\*\*.*?\*\/)/s', $source, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $docblock = $matches[1][0];
            $offset = $matches[1][1];
            $end = strrpos($docblock, '*/');
            if ($end === false) {
                throw new \RuntimeException('Unable to locate the end of the PHP file docblock.');
            }

            $insertion = " * {$tagLine}\n ";
            $updatedDocblock = substr($docblock, 0, $end) . $insertion . substr($docblock, $end);

            return substr($source, 0, $offset)
                . $updatedDocblock
                . substr($source, $offset + strlen($docblock));
        }

        if (preg_match('/<\?php(\s*\R)/', $source, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $fullMatch = $matches[0][0];
            $offset = $matches[0][1] + strlen($fullMatch);
            $header = "/**\n * {$tagLine}\n */\n";

            return substr($source, 0, $offset) . $header . substr($source, $offset);
        }

        throw new \InvalidArgumentException('Source does not contain a PHP opening tag.');
    }

    private function assertPortableDocumentationPath(string $path): void
    {
        if ($path === '') {
            throw new \InvalidArgumentException('Documentation path must not be blank.');
        }
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            throw new \InvalidArgumentException('Documentation path must be relative, not absolute.');
        }
        if (preg_match('#^[A-Za-z][A-Za-z0-9+.-]*://#', $path) === 1) {
            throw new \InvalidArgumentException('Documentation path must be a local relative path, not a URI.');
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                throw new \InvalidArgumentException('Documentation path must not traverse outside the project.');
            }
        }
    }
}
