<?php

declare(strict_types=1);

namespace SourceSlate\Command\Cache;

use SourceSlate\Exception\CacheException;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitCacheOperationLock;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cache:clear', description: 'Clear the SourceSlate Git cache.')]
final class CacheClearCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Confirm deletion without prompting.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable clear results.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maintenanceLock = null;
        $json = (bool) $input->getOption('json');

        try {
            if (!(bool) $input->getOption('yes')) {
                throw new CacheException('SS-CACHE-0010', 'cache:clear requires --yes.', 24);
            }

            $cache = GitCache::default();
            $maintenanceLock = $cache->maintenanceLock();
            $maintenanceLock->acquireExclusive();

            $root = $cache->root();
            if (!is_dir($root)) {
                if ($json) {
                    $output->writeln(json_encode([
                        'status' => 'success',
                        'cleared' => false,
                        'reason' => 'already-empty',
                        'exit_code' => 0,
                    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                } else {
                    $output->writeln('<info>Cache is already empty.</info>');
                }
                return Command::SUCCESS;
            }

            foreach (array_values(array_filter(scandir($root) ?: [], static fn (string $e): bool => $e !== '.' && $e !== '..')) as $entry) {
                $directory = $root . DIRECTORY_SEPARATOR . $entry;
                if (is_dir($directory) && $cache->isRepositoryDirectoryActive($directory)) {
                    throw new CacheException(
                        'SS-CACHE-0025',
                        sprintf('Cannot clear SourceSlate cache while cache entry %s is active.', $entry),
                        24,
                    );
                }
            }

            $this->removeTree($root);
            if ($json) {
                $output->writeln(json_encode([
                    'status' => 'success',
                    'cleared' => true,
                    'exit_code' => 0,
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } else {
                $output->writeln('<info>SourceSlate Git cache cleared.</info>');
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

    private function removeTree(string $path): void
    {
        foreach (array_values(array_filter(scandir($path) ?: [], static fn (string $e): bool => $e !== '.' && $e !== '..')) as $entry) {
            $child = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($child) && !is_link($child)) {
                $this->removeTree($child);
            } elseif (!unlink($child)) {
                throw new CacheException('SS-CACHE-0024', sprintf('Unable to remove cache file: %s', $child), 24);
            }
        }
        if (!rmdir($path)) {
            throw new CacheException('SS-CACHE-0024', sprintf('Unable to remove cache directory: %s', $path), 24);
        }
    }
}
