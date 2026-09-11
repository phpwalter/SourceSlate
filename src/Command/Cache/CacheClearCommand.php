<?php

declare(strict_types=1);

namespace SourceSlate\Command\Cache;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SourceSlate\Source\Git\GitCache;

#[AsCommand(name: 'cache:clear', description: 'Clear the SourceSlate Git cache.')]
final class CacheClearCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Confirm deletion without prompting.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!(bool) $input->getOption('yes')) {
            $output->writeln('<error>SS-CACHE-0010: cache:clear requires --yes.</error>');
            return 24;
        }

        $root = GitCache::default()->root();
        if (!is_dir($root)) {
            $output->writeln('<info>Cache is already empty.</info>');
            return Command::SUCCESS;
        }

        $this->removeTree($root);
        $output->writeln('<info>SourceSlate Git cache cleared.</info>');
        return Command::SUCCESS;
    }

    private function removeTree(string $path): void
    {
        foreach (array_values(array_filter(scandir($path) ?: [], static fn (string $e): bool => $e !== '.' && $e !== '..')) as $entry) {
            $child = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($child) && !is_link($child)) {
                $this->removeTree($child);
            } elseif (!unlink($child)) {
                throw new \RuntimeException(sprintf('Unable to remove cache file: %s', $child));
            }
        }
        if (!rmdir($path)) {
            throw new \RuntimeException(sprintf('Unable to remove cache directory: %s', $path));
        }
    }
}
