<?php

declare(strict_types=1);

namespace SourceSlate\Command\Cache;

use SourceSlate\Source\Git\GitCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cache:list', description: 'List cached Git repositories.')]
final class CacheListCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = GitCache::default()->root();
        if (!is_dir($root)) {
            $output->writeln('<info>No cached repositories.</info>');
            return Command::SUCCESS;
        }

        $entries = array_values(array_filter(scandir($root) ?: [], static fn (string $entry): bool => $entry !== '.' && $entry !== '..'));
        $count = 0;

        foreach ($entries as $entry) {
            $metadataPath = $root . DIRECTORY_SEPARATOR . $entry . DIRECTORY_SEPARATOR . 'metadata.json';
            if (!is_file($metadataPath)) {
                continue;
            }

            $data = json_decode((string) file_get_contents($metadataPath), true);
            if (!is_array($data)) {
                continue;
            }

            $repository = (string) ($data['repository']['canonical_url'] ?? $entry);
            $ref = (string) ($data['state']['last_resolved_ref'] ?? '-');
            $commit = (string) ($data['state']['last_resolved_commit'] ?? '-');
            $lastUsed = (string) ($data['state']['last_used_at'] ?? '-');

            $output->writeln(sprintf('%s | %s | %s | %s', $repository, $ref, $commit !== '-' ? substr($commit, 0, 12) : '-', $lastUsed));
            ++$count;
        }

        if ($count === 0) {
            $output->writeln('<info>No cached repositories.</info>');
        }

        return Command::SUCCESS;
    }
}
