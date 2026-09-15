<?php

declare(strict_types=1);

namespace SourceSlate\Command\Cache;

use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitCacheMetadata;
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

            $result = [
                'entry' => $entry,
                'status' => 'success',
                'code' => null,
                'message' => 'OK',
                'schema_version' => null,
                'legacy_metadata' => false,
                'origin' => null,
                'worktrees' => 0,
            ];

            $metadataPath = $directory . DIRECTORY_SEPARATOR . 'metadata.json';
            if (!is_file($metadataPath)) {
                $results[] = $this->failure($result, 'SS-CACHE-0202', 'Missing metadata.');
                $this->emitFailure($output, $json, 'SS-CACHE-0202', sprintf('Missing metadata for cache entry %s.', $entry));
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
                $result['schema_version'] = GitCacheMetadata::schemaVersion($data);
                $result['legacy_metadata'] = GitCacheMetadata::isLegacy($data);

                $canonicalUrl = $data['repository']['canonical_url'] ?? null;
                if (!is_string($canonicalUrl) || trim($canonicalUrl) === '') {
                    throw new \RuntimeException('Metadata repository.canonical_url is missing.');
                }
                $identity = GitRepositoryIdentity::fromUrl($canonicalUrl);
                if ($identity->cacheKey !== $entry) {
                    throw new \RuntimeException('Metadata repository identity does not match its cache directory.');
                }
                $storedKey = $data['repository']['cache_key'] ?? null;
                if ($storedKey !== null && (string) $storedKey !== $entry) {
                    throw new \RuntimeException('Metadata repository.cache_key does not match its cache directory.');
                }
                $cache->loadMetadata($identity);
            } catch (\Throwable $exception) {
                $results[] = $this->failure($result, 'SS-CACHE-0202', $exception->getMessage());
                $this->emitFailure($output, $json, 'SS-CACHE-0202', sprintf('Invalid metadata for cache entry %s: %s', $entry, $exception->getMessage()));
                continue;
            }

            $repo = $directory . DIRECTORY_SEPARATOR . 'repo.git';
            if (!is_dir($repo)) {
                $results[] = $this->failure($result, 'SS-CACHE-0201', 'Missing bare repository.');
                $this->emitFailure($output, $json, 'SS-CACHE-0201', sprintf('Missing bare repository for cache entry %s.', $entry));
                continue;
            }

            try {
                $git->run(['--git-dir=' . $repo, 'fsck', '--no-dangling']);
            } catch (\Throwable $exception) {
                $results[] = $this->failure($result, 'SS-CACHE-0203', $exception->getMessage());
                $this->emitFailure($output, $json, 'SS-CACHE-0203', $exception->getMessage());
                continue;
            }

            try {
                $origin = trim($git->run(['--git-dir=' . $repo, 'config', '--get', 'remote.origin.url']));
                if ($origin === '') {
                    throw new \RuntimeException('Bare repository has no origin URL.');
                }
                $originIdentity = GitRepositoryIdentity::fromUrl($origin);
                if ($originIdentity->cacheKey !== $entry) {
                    throw new \RuntimeException(sprintf('Bare repository origin resolves to %s, which does not match cache entry %s.', $originIdentity->canonicalUrl, $entry));
                }
                $result['origin'] = $originIdentity->canonicalUrl;
            } catch (\Throwable $exception) {
                $results[] = $this->failure($result, 'SS-CACHE-0204', $exception->getMessage());
                $this->emitFailure($output, $json, 'SS-CACHE-0204', sprintf('Origin identity check failed for %s: %s', $entry, $exception->getMessage()));
                continue;
            }

            try {
                $result['worktrees'] = $this->verifyWorktrees($directory, $git);
            } catch (\Throwable $exception) {
                $results[] = $this->failure($result, 'SS-CACHE-0205', $exception->getMessage());
                $this->emitFailure($output, $json, 'SS-CACHE-0205', sprintf('Worktree verification failed for %s: %s', $entry, $exception->getMessage()));
                continue;
            }

            $results[] = $result;
            if (!$json) {
                $suffix = $result['legacy_metadata'] ? ' (legacy metadata; will upgrade on next write)' : '';
                $output->writeln(sprintf('<info>OK %s%s</info>', $entry, $suffix));
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

    private function verifyWorktrees(string $directory, GitClient $git): int
    {
        $count = 0;
        foreach (['w', 'worktrees'] as $relativeRoot) {
            $root = $directory . DIRECTORY_SEPARATOR . $relativeRoot;
            if (!is_dir($root)) {
                continue;
            }

            foreach (array_diff(scandir($root) ?: [], ['.', '..']) as $name) {
                $path = $root . DIRECTORY_SEPARATOR . $name;
                if (!is_dir($path)) {
                    throw new \RuntimeException(sprintf('Unexpected non-directory worktree cache entry: %s', $path));
                }
                if (!file_exists($path . DIRECTORY_SEPARATOR . '.git')) {
                    throw new \RuntimeException(sprintf('Cached worktree is missing its .git link: %s', $path));
                }
                $inside = strtolower(trim($git->run(['rev-parse', '--is-inside-work-tree'], $path)));
                if ($inside !== 'true') {
                    throw new \RuntimeException(sprintf('Cached worktree is not recognized by Git: %s', $path));
                }
                ++$count;
            }
        }
        return $count;
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    private function failure(array $result, string $code, string $message): array
    {
        $result['status'] = 'error';
        $result['code'] = $code;
        $result['message'] = $message;
        return $result;
    }

    private function emitFailure(OutputInterface $output, bool $json, string $code, string $message): void
    {
        if (!$json) {
            $output->writeln(sprintf('<error>%s: %s</error>', $code, $message));
        }
    }
}
