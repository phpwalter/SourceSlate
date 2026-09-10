<?php

declare(strict_types=1);

namespace SourceSlate\Command\Cache;

use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitRepositoryIdentity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cache:info', description: 'Show metadata for a cached Git repository.')]
final class CacheInfoCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('repository', InputArgument::REQUIRED, 'Git repository URL.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $identity = GitRepositoryIdentity::fromUrl((string) $input->getArgument('repository'));
        $cache = GitCache::default();
        $path = $cache->metadataPath($identity);

        if (!is_file($path)) {
            $output->writeln('<error>SS-CACHE-0404: Repository is not present in the SourceSlate cache.</error>');
            return 24;
        }

        $metadata = $cache->loadMetadata($identity)->toArray();
        $output->writeln(json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
