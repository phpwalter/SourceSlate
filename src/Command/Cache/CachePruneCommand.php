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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be removed without deleting anything.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $seconds = $this->parseAge((string) $input->getOption('older-than'));
            $threshold = time() - $seconds;
            $root = GitCache::default()->root();
            $dryRun = (bool) $input->getOption('dry-run');

            if (!is_dir($root)) {
                $output->writeln('<info>No cached repositories.</info>');
                return Command::SUCCESS;
            }

            $removed = 0;
            foreach (array_values(array_diff(scandir($root) ?: [], ['.', '..'])) as $entry) {
                $directory = $root . DIRECTORY_SEPARATOR . $entry;
                $metadata = $directory . DIRECTORY_SEPARATOR . 'metadata.json';
                if (!is_dir($directory) || !is_file($metadata)) {
                    continue;
                }

                $data = json_decode((string) file_get_contents($metadata), true);
                if (!is_array($data)) {
                    continue;
                }

                $lastUsed = (string) ($data['state']['last_used_at'] ?? '');
                $timestamp = $lastUsed !== '' ? strtotime($lastUsed) : false;
                if ($timestamp === false || $timestamp >= $threshold) {
                    continue;
                }

                $repository = (string) ($data['repository']['canonical_url'] ?? $entry);
                $output->writeln(sprintf('%s %s', $dryRun ? 'Would prune:' : 'Pruning:', $repository));

                if (!$dryRun) {
                    $this->removeTree($directory);
                }
                ++$removed;
            }

            $output->writeln(sprintf('<info>%d cache entr%s %s.</info>', $removed, $removed === 1 ? 'y' : 'ies', $dryRun ? 'would be pruned' : 'pruned'));
            return Command::SUCCESS;
        } catch (CacheException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->formattedMessage()));
            return $exception->exitCode;
        }
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
