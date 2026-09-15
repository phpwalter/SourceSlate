<?php

declare(strict_types=1);

namespace SourceSlate\Command\Cache;

use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitClient;
use SourceSlate\Source\Git\GitRepositoryIdentity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cache:verify', description: 'Verify cached Git repositories.')]
final class CacheVerifyCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable verification results.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = (bool) $input->getOption('json');
        $cache = GitCache::default();
        $root = $cache->root();
        if (!is_dir($root)) {
            if ($json) {
                $output->writeln(json_encode(['status' => 'success', 'failures' => 0, 'entries' => []], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } else {
                $output->writeln('<info>No cached repositories.</info>');
            }
            return Command::SUCCESS;
        }

        $git = new GitClient();
        $results = [];
        foreach (array_values(array_filter(scandir($root) ?: [], static fn (string $e): bool => $e !== '.' && $e !== '..')) as $entry) {
            $directory = $root . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($directory)) {
                continue;
            }

            $result = ['entry' => $entry, 'status' => 'success', 'code' => null, 'message' => 'OK'];
            $metadataPath = $directory . DIRECTORY_SEPARATOR . 'metadata.json';
            if (!is_file($metadataPath)) {
                $result = ['entry' => $entry, 'status' => 'error', 'code' => 'SS-CACHE-0202', 'message' => 'Missing metadata.'];
                $results[] = $result;
                if (!$json) {
                    $output->writeln(sprintf('<error>SS-CACHE-0202: Missing metadata for cache entry %s.</error>', $entry));
                }
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
                $result = ['entry' => $entry, 'status' => 'error', 'code' => 'SS-CACHE-0202', 'message' => $exception->getMessage()];
                $results[] = $result;
                if (!$json) {
                    $output->writeln(sprintf('<error>SS-CACHE-0202: Invalid metadata for cache entry %s: %s</error>', $entry, $exception->getMessage()));
                }
                continue;
            }

            $repo = $directory . DIRECTORY_SEPARATOR . 'repo.git';
            if (!is_dir($repo)) {
                $result = ['entry' => $entry, 'status' => 'error', 'code' => 'SS-CACHE-0201', 'message' => 'Missing bare repository.'];
                $results[] = $result;
                if (!$json) {
                    $output->writeln(sprintf('<error>SS-CACHE-0201: Missing bare repository for cache entry %s.</error>', $entry));
                }
                continue;
            }

            try {
                $git->run(['--git-dir=' . $repo, 'fsck', '--no-dangling']);
                $results[] = $result;
                if (!$json) {
                    $output->writeln(sprintf('<info>OK %s</info>', $entry));
                }
            } catch (\Throwable $exception) {
                $result = ['entry' => $entry, 'status' => 'error', 'code' => 'SS-CACHE-0203', 'message' => $exception->getMessage()];
                $results[] = $result;
                if (!$json) {
                    $output->writeln(sprintf('<error>SS-CACHE-0203: %s</error>', $exception->getMessage()));
                }
            }
        }

        $failures = count(array_filter($results, static fn (array $result): bool => $result['status'] === 'error'));
        if ($json) {
            $output->writeln(json_encode([
                'status' => $failures === 0 ? 'success' : 'error',
                'failures' => $failures,
                'entries' => $results,
                'exit_code' => $failures === 0 ? 0 : 24,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } elseif ($failures > 0) {
            $output->writeln(sprintf('<error>SS-CACHE-0203: %d cache entr%s failed verification.</error>', $failures, $failures === 1 ? 'y' : 'ies'));
        }

        return $failures === 0 ? Command::SUCCESS : 24;
    }
}
