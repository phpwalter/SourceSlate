<?php

declare(strict_types=1);

namespace SourceSlate\Command\Cache;

use SourceSlate\Exception\CacheException;
use SourceSlate\Exception\SourceSlateException;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitCacheMetadata;
use SourceSlate\Source\Git\GitClient;
use SourceSlate\Source\Git\GitRepositoryIdentity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cache:repair', description: 'Rebuild one SourceSlate Git cache entry.')]
final class CacheRepairCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('repository', InputArgument::REQUIRED, 'Git repository URL to repair.')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Confirm replacement of the cached repository.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if (!(bool) $input->getOption('yes')) {
                throw new CacheException('SS-CACHE-0010', 'cache:repair requires --yes because it replaces the selected cached repository.', 24);
            }

            $identity = GitRepositoryIdentity::fromUrl((string) $input->getArgument('repository'));
            $cache = GitCache::default();
            $directory = $cache->repositoryDirectory($identity);

            if (is_dir($directory)) {
                $this->removeTree($directory);
            }

            $cache->ensureRepositoryDirectory($identity);
            (new GitClient())->run(['clone', '--bare', $identity->originalUrl, $cache->bareRepository($identity)]);
            $cache->saveMetadata(GitCacheMetadata::create($identity));

            $output->writeln(sprintf('<info>Rebuilt cache for %s.</info>', $identity->canonicalUrl));
            return Command::SUCCESS;
        } catch (SourceSlateException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->formattedMessage()));
            return $exception->exitCode;
        }
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
                throw new CacheException('SS-CACHE-0024', sprintf('Unable to remove cache path during repair: %s', $item->getPathname()), 24);
            }
        }

        if (!rmdir($path)) {
            throw new CacheException('SS-CACHE-0024', sprintf('Unable to remove cache directory during repair: %s', $path), 24);
        }
    }
}
