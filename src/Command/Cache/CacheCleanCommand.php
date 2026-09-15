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

#[AsCommand(name: 'cache:clean', description: 'Remove stale repair artifacts and released cache lock files.')]
final class CacheCleanCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Remove debris older than this age, such as 24h or 7d.', '24h')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report cleanup candidates without deleting them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $age = $this->parseAge((string) $input->getOption('older-than'));
            $threshold = time() - $age;
            $dryRun = (bool) $input->getOption('dry-run');
            $cache = GitCache::default();
            $maintenance = $cache->maintenanceLock();
            $maintenance->acquireShared();

            try {
                $candidates = array_merge(
                    $this->repairArtifacts($cache->root(), $threshold),
                    $this->releasedLocks($cache->root() . '.locks', $threshold),
                );
                sort($candidates, SORT_STRING);
                foreach ($candidates as $candidate) {
                    $output->writeln(sprintf('%s %s', $dryRun ? 'Would remove:' : 'Removing:', $candidate));
                    if (!$dryRun) {
                        $this->removePath($candidate);
                    }
                }
                $count = count($candidates);
                $output->writeln(sprintf('<info>%d stale cache artifact%s %s.</info>', $count, $count === 1 ? '' : 's', $dryRun ? 'would be removed' : 'removed'));
                return Command::SUCCESS;
            } finally {
                $maintenance->release();
            }
        } catch (CacheException $exception) {
            $output->writeln('<error>' . $exception->formattedMessage() . '</error>');
            return $exception->exitCode;
        }
    }

    /** @return list<string> */
    private function repairArtifacts(string $root, int $threshold): array
    {
        if (!is_dir($root)) {
            return [];
        }
        $candidates = [];
        foreach (array_diff(scandir($root) ?: [], ['.', '..']) as $entry) {
            if (!str_contains($entry, '.repair-') && !str_contains($entry, '.repair-backup-')) {
                continue;
            }
            $path = $root . DIRECTORY_SEPARATOR . $entry;
            $mtime = filemtime($path);
            if ($mtime !== false && $mtime < $threshold) {
                $candidates[] = $path;
            }
        }
        return $candidates;
    }

    /** @return list<string> */
    private function releasedLocks(string $root, int $threshold): array
    {
        if (!is_dir($root)) {
            return [];
        }
        $candidates = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if (!$item->isFile() || !str_ends_with($item->getFilename(), '.lock') || $item->getMTime() >= $threshold) {
                continue;
            }
            $handle = @fopen($item->getPathname(), 'c+');
            if ($handle === false) {
                continue;
            }
            $acquired = flock($handle, LOCK_EX | LOCK_NB);
            if ($acquired) {
                flock($handle, LOCK_UN);
                $candidates[] = $item->getPathname();
            }
            fclose($handle);
        }
        return $candidates;
    }

    private function removePath(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $item) {
                $ok = $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
                if (!$ok) {
                    throw new CacheException('SS-CACHE-0024', sprintf('Unable to remove stale cache path: %s', $item->getPathname()), 24);
                }
            }
            if (!@rmdir($path)) {
                throw new CacheException('SS-CACHE-0024', sprintf('Unable to remove stale cache directory: %s', $path), 24);
            }
            return;
        }
        if (!@unlink($path)) {
            throw new CacheException('SS-CACHE-0024', sprintf('Unable to remove stale cache file: %s', $path), 24);
        }
    }

    private function parseAge(string $value): int
    {
        if (preg_match('/^(\d+)([dhm])$/i', trim($value), $matches) !== 1) {
            throw new CacheException('SS-CACHE-0011', 'Invalid --older-than value. Use values such as 7d, 24h, or 30m.', 24);
        }
        $amount = (int) $matches[1];
        return $amount * match (strtolower($matches[2])) {
            'd' => 86400,
            'h' => 3600,
            'm' => 60,
        };
    }
}
