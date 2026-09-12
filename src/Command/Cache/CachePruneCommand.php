<?php

declare(strict_types=1);

namespace SourceSlate\Command\Cache;

use SourceSlate\Exception\CacheException;
use SourceSlate\Source\Git\GitCache;
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be removed without deleting anything.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $seconds = $this->parseAge((string) $input->getOption('older-than'));
            $threshold = time() - $seconds;
            $maxSize = $input->getOption('max-size') !== null ? $this->parseSize((string) $input->getOption('max-size')) : null;
            $cache = GitCache::default();
            $root = $cache->root();
            $dryRun = (bool) $input->getOption('dry-run');

            if (!is_dir($root)) {
                $output->writeln('<info>No cached repositories.</info>');
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
            foreach ($selected as $entry) {
                $output->writeln(sprintf(
                    '%s %s (%s)',
                    $dryRun ? 'Would prune:' : 'Pruning:',
                    $entry['repository'],
                    $this->formatBytes($entry['size']),
                ));

                if (!$dryRun) {
                    $this->removeTree($entry['directory']);
                }
            }

            foreach ($entries as $entry) {
                if ($entry['active']) {
                    $output->writeln(sprintf('<comment>Skipping active cache entry: %s</comment>', $entry['repository']));
                }
            }

            $count = count($selected);
            $output->writeln(sprintf('<info>%d cache entr%s %s.</info>', $count, $count === 1 ? 'y' : 'ies', $dryRun ? 'would be pruned' : 'pruned'));
            return Command::SUCCESS;
        } catch (CacheException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->formattedMessage()));
            return $exception->exitCode;
        }
    }

    /** @return list<array{directory:string,repository:string,last_used:int,size:int,active:bool}> */
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
            if ($lastUsed === false) {
                continue;
            }

            $entries[] = [
                'directory' => $directory,
                'repository' => (string) ($data['repository']['canonical_url'] ?? $name),
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
