<?php

declare(strict_types=1);

namespace SourceSlate\Command\Cache;

use SourceSlate\Exception\CacheException;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitCacheOperationLock;
use SourceSlate\Source\Git\GitRepositoryIdentity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cache:prune', description: 'Prune old SourceSlate Git cache entries.')]
final class CachePruneCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Remove repositories not used within this age, such as 90d.', '90d')
            ->addOption('max-size', null, InputOption::VALUE_REQUIRED, 'Reduce cache to at most this size, such as 10GB.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be removed without deleting anything.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable prune results.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maintenanceLock = null;
        $json = (bool) $input->getOption('json');

        try {
            $seconds = $this->parseAge((string) $input->getOption('older-than'));
            $threshold = time() - $seconds;
            $maxSize = $input->getOption('max-size') !== null ? $this->parseSize((string) $input->getOption('max-size')) : null;
            $cache = GitCache::default();
            $root = $cache->root();
            $dryRun = (bool) $input->getOption('dry-run');

            $maintenanceLock = $cache->maintenanceLock();
            $maintenanceLock->acquireShared();

            if (!is_dir($root)) {
                if ($json) {
                    $output->writeln(json_encode([
                        'status' => 'success',
                        'mode' => $dryRun ? 'dry-run' : 'prune',
                        'selected' => [],
                        'skipped_active' => [],
                        'count' => 0,
                        'exit_code' => 0,
                    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                } else {
                    $output->writeln('<info>No cached repositories.</info>');
                }
                return Command::SUCCESS;
            }

            $entries = $this->entries($root, $cache);
            $selected = [];

            foreach ($entries as $entry) {
                if (!$entry['active'] && $entry['last_used'] < $threshold) {
                    $selected[$entry['directory']] = $entry;
                }
            }

            if ($maxSize !== null) {
                $remaining = array_values(array_filter(
                    $entries,
                    static fn (array $entry): bool => !isset($selected[$entry['directory']]),
                ));
                $remainingSize = array_sum(array_column($remaining, 'size'));
                usort($remaining, static fn (array $a, array $b): int => $a['last_used'] <=> $b['last_used']);

                foreach ($remaining as $entry) {
                    if ($remainingSize <= $maxSize) {
                        break;
                    }
                    if ($entry['active']) {
                        continue;
                    }
                    $selected[$entry['directory']] = $entry;
                    $remainingSize -= $entry['size'];
                }
            }

            uasort($selected, static fn (array $a, array $b): int => $a['last_used'] <=> $b['last_used']);
            $processed = [];
            foreach ($selected as $entry) {
                if (!$json) {
                    $output->writeln(sprintf(
                        '%s %s (%s)',
                        $dryRun ? 'Would prune:' : 'Pruning:',
                        $entry['repository'],
                        $this->formatBytes($entry['size']),
                    ));
                }

                $removed = false;
                if (!$dryRun) {
                    $identity = GitRepositoryIdentity::fromUrl($entry['repository_url']);
                    $operationLock = $cache->operationLock($identity);
                    $operationLock->acquireExclusive();
                    try {
                        if ($cache->isRepositoryDirectoryActive($entry['directory'])) {
                            if (!$json) {
                                $output->writeln(sprintf('<comment>Skipping active cache entry: %s</comment>', $entry['repository']));
                            }
                            continue;
                        }
                        $this->removeTree($entry['directory']);
                        $removed = true;
                    } finally {
                        $operationLock->release();
                    }
                }

                $processed[] = [
                    'repository' => $entry['repository'],
                    'size_bytes' => $entry['size'],
                    'last_used' => gmdate('c', $entry['last_used']),
                    'removed' => $removed,
                ];
            }

            $active = array_values(array_map(
                static fn (array $entry): string => $entry['repository'],
                array_filter($entries, static fn (array $entry): bool => $entry['active']),
            ));

            if ($json) {
                $output->writeln(json_encode([
                    'status' => 'success',
                    'mode' => $dryRun ? 'dry-run' : 'prune',
                    'older_than' => (string) $input->getOption('older-than'),
                    'max_size' => $input->getOption('max-size'),
                    'selected' => $processed,
                    'skipped_active' => $active,
                    'count' => count($processed),
                    'exit_code' => 0,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } else {
                foreach ($active as $repository) {
                    $output->writeln(sprintf('<comment>Skipping active cache entry: %s</comment>', $repository));
                }
                $count = count($selected);
                $output->writeln(sprintf('<info>%d cache entr%s %s.</info>', $count, $count === 1 ? 'y' : 'ies', $dryRun ? 'would be pruned' : 'selected for pruning'));
            }
            return Command::SUCCESS;
        } catch (CacheException $exception) {
            if ($json) {
                $output->writeln(json_encode([
                    'status' => 'error',
                    'code' => $exception->diagnosticCode,
                    'message' => $exception->getMessage(),
                    'exit_code' => $exception->exitCode,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } else {
                $output->writeln(sprintf('<error>%s</error>', $exception->formattedMessage()));
            }
            return $exception->exitCode;
        } finally {
            if ($maintenanceLock instanceof GitCacheOperationLock) {
                $maintenanceLock->release();
            }
        }
    }

    /** @return list<array{directory:string,repository:string,repository_url:string,last_used:int,size:int,active:bool}> */
    private function entries(string $root, GitCache $cache): array
    {
        $entries = [];
        foreach (array_values(array_diff(scandir($root) ?: [], ['.', '..'])) as $name) {
            $directory = $root . DIRECTORY_SEPARATOR . $name;
            $metadata = $directory . DIRECTORY_SEPARATOR . 'metadata.json';
            if (!is_dir($directory) || !is_file($metadata)) {
                continue;
            }

            $data = json_decode((string) file_get_contents($metadata), true);
            if (!is_array($data)) {
                continue;
            }

            $lastUsedValue = (string) ($data['state']['last_used_at'] ?? '');
            $lastUsed = $lastUsedValue !== '' ? strtotime($lastUsedValue) : false;
            $repositoryUrl = (string) ($data['repository']['transport_url'] ?? $data['repository']['canonical_url'] ?? '');
            if ($lastUsed === false || $repositoryUrl === '') {
                continue;
            }

            $entries[] = [
                'directory' => $directory,
                'repository' => (string) ($data['repository']['canonical_url'] ?? $name),
                'repository_url' => $repositoryUrl,
                'last_used' => $lastUsed,
                'size' => $this->directorySize($directory),
                'active' => $cache->isRepositoryDirectoryActive($directory),
            ];
        }

        return $entries;
    }

    private function parseAge(string $value): int
    {
        if (preg_match('/^(\d+)([dhm])$/i', trim($value), $matches) !== 1) {
            throw new CacheException('SS-CACHE-0011', 'Invalid --older-than value. Use values such as 90d, 24h, or 30m.', 24);
        }

        $amount = (int) $matches[1];
        return $amount * match (strtolower($matches[2])) {
            'd' => 86400,
            'h' => 3600,
            'm' => 60,
        };
    }

    private function parseSize(string $value): int
    {
        if (preg_match('/^(\d+)(kb|mb|gb|tb)$/i', trim($value), $matches) !== 1) {
            throw new CacheException('SS-CACHE-0012', 'Invalid --max-size value. Use values such as 500MB or 10GB.', 24);
        }

        $amount = (int) $matches[1];
        return $amount * match (strtolower($matches[2])) {
            'kb' => 1024,
            'mb' => 1024 ** 2,
            'gb' => 1024 ** 3,
            'tb' => 1024 ** 4,
        };
    }

    private function directorySize(string $path): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $size += $item->getSize();
            }
        }
        return $size;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $index = 0;
        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            ++$index;
        }
        return sprintf('%.1f%s', $value, $units[$index]);
    }

    private function removeTree(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $ok = $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            if (!$ok) {
                throw new CacheException('SS-CACHE-0024', sprintf('Unable to prune cache path: %s', $item->getPathname()), 24);
            }
        }

        if (!rmdir($path)) {
            throw new CacheException('SS-CACHE-0024', sprintf('Unable to prune cache directory: %s', $path), 24);
        }
    }
}
