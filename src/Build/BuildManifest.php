<?php

declare(strict_types=1);

namespace SourceSlate\Build;

use SourceSlate\Source\SourceWorkspace;

final readonly class BuildManifest
{
    public function __construct(
        public string $buildId,
        public string $buildTime,
        public string $sourceType,
        public string $sourceRoot,
        public ?string $repository,
        public ?string $requestedRef,
        public ?string $resolvedCommit,
        public bool $offline,
        public array $files,
    ) {
    }

    public static function create(SourceWorkspace $workspace, string $outputDirectory): self
    {
        return new self(
            buildId: bin2hex(random_bytes(16)),
            buildTime: gmdate('c'),
            sourceType: $workspace->remote ? 'git' : 'local',
            sourceRoot: $workspace->root,
            repository: $workspace->repository,
            requestedRef: $workspace->requestedRef,
            resolvedCommit: $workspace->resolvedCommit,
            offline: $workspace->offline,
            files: self::hashGeneratedFiles($outputDirectory),
        );
    }

    public function write(string $outputDirectory): void
    {
        $path = rtrim($outputDirectory, '\\/') . DIRECTORY_SEPARATOR . '.sourceslate-manifest.json';
        $data = [
            'schema_version' => 1,
            'build_id' => $this->buildId,
            'build_time' => $this->buildTime,
            'source' => [
                'type' => $this->sourceType,
                'root' => $this->sourceRoot,
                'repository' => $this->repository,
                'requested_ref' => $this->requestedRef,
                'resolved_commit' => $this->resolvedCommit,
                'offline' => $this->offline,
            ],
            'files' => $this->files,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Unable to write SourceSlate manifest: %s', $path));
        }
    }

    private static function hashGeneratedFiles(string $outputDirectory): array
    {
        $files = [];
        if (!is_dir($outputDirectory)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($outputDirectory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            if (basename($path) === '.sourceslate-manifest.json') {
                continue;
            }

            $relative = substr($path, strlen(rtrim($outputDirectory, '\\/')) + 1);
            $files[] = [
                'path' => str_replace(DIRECTORY_SEPARATOR, '/', $relative),
                'sha256' => hash_file('sha256', $path),
            ];
        }

        usort($files, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);

        return $files;
    }
}
