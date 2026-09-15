<?php

declare(strict_types=1);

namespace SourceSlate\Build;

interface PublicationFilesystem
{
    public function exists(string $path): bool;

    public function rename(string $from, string $to): bool;

    public function unlink(string $path): bool;
}
