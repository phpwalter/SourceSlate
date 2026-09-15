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
}
