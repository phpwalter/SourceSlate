<?php

declare(strict_types=1);

namespace SourceSlate\Build;

final readonly class NativePublicationFilesystem implements PublicationFilesystem
{
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function rename(string $from, string $to): bool
    {
        return @rename($from, $to);
    }

    public function unlink(string $path): bool
    {
        return @unlink($path);
    }
}
