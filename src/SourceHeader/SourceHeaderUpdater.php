<?php

declare(strict_types=1);

namespace SourceSlate\SourceHeader;

final class SourceHeaderUpdater
{
    public function update(string $source, string $documentationPath): string
    {
        $documentationPath = trim(str_replace('\\', '/', $documentationPath));
        if ($documentationPath === '') {
            throw new \InvalidArgumentException('Documentation path must not be blank.');
        }

        $tagLine = '@sourceslate ' . $documentationPath;

        if (preg_match('/@sourceslate\s+[^\r\n*]+/', $source) === 1) {
            return preg_replace('/@sourceslate\s+[^\r\n*]+/', $tagLine, $source, 1) ?? $source;
        }

        if (preg_match('/<\?php\s*\R\s*\/\*\*/', $source) === 1) {
            return preg_replace('/(<\?php\s*\R\s*\/\*\*)(\s*)/', "$1\n * {$tagLine}\n */$2", $source, 1) ?? $source;
        }

        if (preg_match('/<\?php\s*\R\s*\/\*\*/', $source) !== 1 && preg_match('/<\?php\s*\R\s*\/\*\*/', $source) === 0) {
            if (preg_match('/<\?php\s*\R\s*(\/\*\*.*?\*\/)/s', $source, $matches) === 1) {
                $docblock = $matches[1];
                $replacement = preg_replace('/\*\/$/', " * {$tagLine}\n */", $docblock, 1) ?? $docblock;
                return preg_replace('/' . preg_quote($docblock, '/') . '/', str_replace('\\', '\\\\', $replacement), $source, 1) ?? $source;
            }
        }

        if (preg_match('/<\?php(\s*\R)/', $source, $matches) === 1) {
            $header = "/**\n * {$tagLine}\n */\n";
            return preg_replace('/<\?php(\s*\R)/', "<?php$1$header", $source, 1) ?? $source;
        }

        throw new \InvalidArgumentException('Source does not contain a PHP opening tag.');
    }
}
