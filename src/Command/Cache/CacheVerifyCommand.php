<?php

declare(strict_types=1);

namespace SourceSlate\Command\Cache;

use SourceSlate\Exception\CacheException;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitClient;
use SourceSlate\Source\Git\GitRepositoryIdentity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cache:verify', description: 'Verify cached Git repositories.')]
final class CacheVerifyCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cache = GitCache::default();
        $root = $cache->root();
        if (!is_dir($root)) {
            $output->writeln('<info>No cached repositories.</info>');
            return Command::SUCCESS;
        }

        $git = new GitClient();
        $failures = 0;
        foreach (array_values(array_filter(scandir($root) ?: [], static fn (string $e): bool => $e !== '.' && $e !== '..')) as $entry) {
            $directory = $root . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($directory)) {
                continue;
            }

            $metadataPath = $directory . DIRECTORY_SEPARATOR . 'metadata.json';
            if (!is_file($metadataPath)) {
                ++$failures;
                $output->writeln(sprintf('<error>SS-CACHE-0202: Missing metadata for cache entry %s.</error>', $entry));
                continue;
            }

            try {
                $raw = file_get_contents($metadataPath);
                if ($raw === false) {
                    throw new \RuntimeException('Unable to read metadata.');
                }
                $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($data)) {
                    throw new \RuntimeException('Metadata root must be an object.');
                }

                $canonicalUrl = $data['repository']['canonical_url'] ?? null;
                if (!is_string($canonicalUrl) || trim($canonicalUrl) === '') {
                    throw new \RuntimeException('Metadata repository.canonical_url is missing.');
                }

                $identity = GitRepositoryIdentity::fromUrl($canonicalUrl);
                if ($identity->cacheKey !== $entry) {
                    throw new \RuntimeException('Metadata repository identity does not match its cache directory.');
                }

                $cache->loadMetadata($identity);
            } catch (\Throwable $exception) {
                ++$failures;
                $output->writeln(sprintf('<error>SS-CACHE-0202: Invalid metadata for cache entry %s: %s</error>', $entry, $exception->getMessage()));
                continue;
            }

            $repo = $directory . DIRECTORY_SEPARATOR . 'repo.git';
            if (!is_dir($repo)) {
                ++$failures;
                $output->writeln(sprintf('<error>SS-CACHE-0201: Missing bare repository for cache entry %s.</error>', $entry));
                continue;
            }

            try {
                $git->run(['--git-dir=' . $repo, 'fsck', '--no-dangling']);
                $output->writeln(sprintf('<info>OK %s</info>', $entry));
            } catch (\Throwable $exception) {
                ++$failures;
                $output->writeln(sprintf('<error>SS-CACHE-0203: %s</error>', $exception->getMessage()));
            }
        }

        if ($failures > 0) {
            throw new CacheException('SS-CACHE-0203', sprintf('%d cache entr%s failed verification.', $failures, $failures === 1 ? 'y' : 'ies'), 24);
        }

        return Command::SUCCESS;
    }
}
