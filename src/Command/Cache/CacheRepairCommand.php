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
        $candidate = null;

        try {
            if (!(bool) $input->getOption('yes')) {
                throw new CacheException('SS-CACHE-0010', 'cache:repair requires --yes because it replaces the selected cached repository.', 24);
            }

            $identity = GitRepositoryIdentity::fromUrl((string) $input->getArgument('repository'));
            $cache = GitCache::default();
            $directory = $cache->repositoryDirectory($identity);

            if (is_dir($directory) && $cache->isActive($identity)) {
                throw new CacheException(
                    'SS-CACHE-0025',
                    sprintf('Cannot repair cache for %s while it is active.', $identity->canonicalUrl),
                    24,
                );
            }

            $candidate = $directory . '.repair-' . bin2hex(random_bytes(6));
            $candidateBare = $candidate . DIRECTORY_SEPARATOR . 'repo.git';
            $candidateMetadata = $candidate . DIRECTORY_SEPARATOR . 'metadata.json';

            if (!mkdir($candidate, 0777, true) && !is_dir($candidate)) {
                throw new CacheException('SS-CACHE-0024', sprintf('Unable to create repair staging directory: %s', $candidate), 24);
            }

            $git = new GitClient();
            $git->run(['clone', '--bare', $identity->originalUrl, $candidateBare]);
            $git->run(['--git-dir=' . $candidateBare, 'fsck', '--no-dangling']);

            $metadata = GitCacheMetadata::create($identity);
            $json = json_encode($metadata->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
            if (file_put_contents($candidateMetadata, $json, LOCK_EX) === false) {
                throw new CacheException('SS-CACHE-0024', sprintf('Unable to write repair metadata: %s', $candidateMetadata), 24);
            }

            $backup = null;
            if (is_dir($directory)) {
                $backup = $directory . '.repair-backup-' . bin2hex(random_bytes(6));
                if (!rename($directory, $backup)) {
                    throw new CacheException('SS-CACHE-0024', sprintf('Unable to preserve existing cache during repair: %s', $directory), 24);
                }
            }

            if (!rename($candidate, $directory)) {
                if ($backup !== null && !is_dir($directory)) {
                    @rename($backup, $directory);
                }
                throw new CacheException('SS-CACHE-0024', sprintf('Unable to publish repaired cache: %s', $directory), 24);
            }
            $candidate = null;

            if ($backup !== null) {
                $this->removeTree($backup);
            }

            $output->writeln(sprintf('<info>Rebuilt cache for %s.</info>', $identity->canonicalUrl));
            return Command::SUCCESS;
        } catch (SourceSlateException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->formattedMessage()));
            return $exception->exitCode;
        } finally {
            if ($candidate !== null && is_dir($candidate)) {
                $this->removeTreeQuietly($candidate);
            }
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

    private function removeTreeQuietly(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink()
                ? @rmdir($item->getPathname())
                : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
