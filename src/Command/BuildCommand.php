<?php

/**
 * @file BuildCommand.php
 * @path src/Command/BuildCommand.php
 * @version 1.10.0
 * @date 2026-09-11
 * @author Walter Torres
 * @copyright Copyright 2026, Walter Torres.
 * @license Proprietary
 * @maintainer SourceSlate Team
 * @status dev
 *
 * Implements the SourceSlate documentation build command.
 */

declare(strict_types=1);

namespace SourceSlate\Command;

use SourceSlate\Build\BuildManifest;
use SourceSlate\Build\BuildStagingArea;
use SourceSlate\Build\DocumentationComparator;
use SourceSlate\Build\OutputGuard;
use SourceSlate\Configuration\ConfigurationLoader;
use SourceSlate\Exception\OutputException;
use SourceSlate\Exception\SourceSlateException;
use SourceSlate\Exception\ValidationException;
use SourceSlate\Parser\PhpSourceParser;
use SourceSlate\Renderer\HtmlRenderer;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitClient;
use SourceSlate\Source\Git\GitSourceProvider;
use SourceSlate\Source\SourceRequest;
use SourceSlate\Source\SourceResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'build', description: 'Build static SourceSlate documentation.')]
final class BuildCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('project', InputArgument::OPTIONAL, 'Local project root or remote Git repository.', '.')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Explicit SourceSlate YAML configuration file.')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output directory. Required for remote Git sources.')
            ->addOption('force-output', null, InputOption::VALUE_NONE, 'Allow replacement of a non-empty output directory that is not already SourceSlate-managed.')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Remote Git branch to document.')
            ->addOption('tag', null, InputOption::VALUE_REQUIRED, 'Remote Git tag to document.')
            ->addOption('ref', null, InputOption::VALUE_REQUIRED, 'Remote Git branch ref, tag ref, or commit SHA to document.')
            ->addOption('source-type', null, InputOption::VALUE_REQUIRED, 'Force source interpretation: local or git.')
            ->addOption('source-path', null, InputOption::VALUE_REQUIRED, 'Subdirectory within the resolved source workspace.')
            ->addOption('git-timeout', null, InputOption::VALUE_REQUIRED, 'Git command timeout in seconds. Overrides SOURCESLATE_GIT_TIMEOUT.')
            ->addOption('refresh', null, InputOption::VALUE_NONE, 'Force remote revalidation when supported.')
            ->addOption('offline', null, InputOption::VALUE_NONE, 'Use only the persistent Git cache; never contact the remote.')
            ->addOption('recurse-submodules', null, InputOption::VALUE_NONE, 'Fetch submodules for the resolved worktree.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Resolve source/configuration and report the planned build without rendering or publishing.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON output.')
            ->addOption('ci', null, InputOption::VALUE_NONE, 'Use deterministic non-interactive CI behavior.')
            ->addOption('update-source', null, InputOption::VALUE_NONE, 'Update source headers with @sourceslate links when supported.')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Render in staging and fail if published documentation differs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $staging = null;
        $json = (bool) $input->getOption('json');

        try {
            $gitTimeout = $this->resolveGitTimeout($input->getOption('git-timeout'));
            $request = new SourceRequest(
                source: (string) $input->getArgument('project'),
                output: $input->getOption('output') !== null ? (string) $input->getOption('output') : null,
                branch: $input->getOption('branch') !== null ? (string) $input->getOption('branch') : null,
                tag: $input->getOption('tag') !== null ? (string) $input->getOption('tag') : null,
                ref: $input->getOption('ref') !== null ? (string) $input->getOption('ref') : null,
                sourcePath: $input->getOption('source-path') !== null ? (string) $input->getOption('source-path') : null,
                refresh: (bool) $input->getOption('refresh'),
                offline: (bool) $input->getOption('offline'),
                recurseSubmodules: (bool) $input->getOption('recurse-submodules'),
                sourceType: $input->getOption('source-type') !== null ? (string) $input->getOption('source-type') : null,
                gitTimeout: $gitTimeout,
            );

            $cache = GitCache::default();
            $git = new GitClient($request->gitTimeout);
            $resolver = new SourceResolver(new GitSourceProvider($git, $cache));
            $workspace = $resolver->resolve($request);
            $root = $workspace->root;

            $configPath = $input->getOption('config') !== null ? (string) $input->getOption('config') : null;
            $config = (new ConfigurationLoader())->load($root, $configPath);

            if ((bool) $input->getOption('update-source')) {
                if ($workspace->remote) {
                    throw new SourceSlateException('SS-SRC-0010', '--update-source is not permitted for remote Git sources.', 31);
                }
                if (!$json) {
                    $output->writeln('<comment>Source-header mutation is reserved by the 1.0 contract but is not enabled in this foundation build.</comment>');
                }
            }

            $outputDirectory = $request->output !== null
                ? $this->absoluteOutputPath($request->output)
                : $root . DIRECTORY_SEPARATOR . $config->outputPath;

            if ($workspace->remote) {
                $this->assertRemoteOutputSafety($outputDirectory, $root, $cache->root());
            }

            if ((bool) $input->getOption('dry-run')) {
                $this->emitDryRun($output, $json, $workspace, $configPath, $config, $outputDirectory);
                return Command::SUCCESS;
            }

            $project = (new PhpSourceParser())->parse($root, $config);

            if ((bool) $input->getOption('check')) {
                $staging = new BuildStagingArea($outputDirectory);
                (new HtmlRenderer())->render($project, $staging->path());
                $comparison = (new DocumentationComparator())->compare($staging->path(), $outputDirectory);
                $staging->discard();
                $staging = null;

                if (!$comparison['matches']) {
                    if ($json) {
                        $this->writeJson($output, [
                            'status' => 'error',
                            'code' => 'SS-DOC-0050',
                            'message' => 'Published documentation is stale.',
                            'exit_code' => 50,
                            'differences' => $comparison,
                        ]);
                        return 50;
                    }

                    throw new ValidationException('SS-DOC-0050', sprintf(
                        'Published documentation is stale. Missing: %d; unexpected: %d; changed: %d.',
                        count($comparison['missing']),
                        count($comparison['unexpected']),
                        count($comparison['changed']),
                    ), 50);
                }

                if ($json) {
                    $this->writeJson($output, ['status' => 'success', 'mode' => 'check', 'documentation' => ['up_to_date' => true]]);
                } else {
                    $output->writeln('<info>Documentation is up to date.</info>');
                }

                return Command::SUCCESS;
            }

            (new OutputGuard())->assertWritable($outputDirectory, (bool) $input->getOption('force-output'));

            $staging = new BuildStagingArea($outputDirectory);
            (new HtmlRenderer())->render($project, $staging->path());
            BuildManifest::create($workspace, $staging->path())->write($staging->path());
            $staging->publish();
            $staging = null;

            if ($json) {
                $this->writeJson($output, [
                    'status' => 'success',
                    'source' => [
                        'type' => $workspace->remote ? 'git' : 'local',
                        'repository' => $workspace->repository,
                        'branch' => $workspace->branch,
                        'requested_ref' => $workspace->requestedRef,
                        'resolved_commit' => $workspace->resolvedCommit,
                        'git_state' => $workspace->gitState,
                        'cache_status' => $workspace->cacheStatus,
                        'offline' => $workspace->offline,
                    ],
                    'documentation' => [
                        'files_scanned' => count($project->files),
                        'output' => $outputDirectory,
                    ],
                ]);
            } else {
                if ($workspace->remote) {
                    $output->writeln(sprintf(
                        '<info>Resolved %s at %s (cache: %s).</info>',
                        $workspace->repository,
                        $workspace->resolvedCommit,
                        $workspace->cacheStatus ?? 'unknown',
                    ));
                }

                $output->writeln(sprintf(
                    '<info>SourceSlate generated documentation for %d PHP file(s) in %s.</info>',
                    count($project->files),
                    $outputDirectory,
                ));
            }

            return Command::SUCCESS;
        } catch (SourceSlateException $exception) {
            if ($staging instanceof BuildStagingArea) {
                $staging->discard();
            }

            if ($json) {
                $this->writeJson($output, [
                    'status' => 'error',
                    'code' => $exception->diagnosticCode,
                    'message' => $exception->getMessage(),
                    'exit_code' => $exception->exitCode,
                ]);
            } else {
                $output->writeln(sprintf('<error>%s</error>', $exception->formattedMessage()));
            }

            return $exception->exitCode;
        } catch (\Throwable $exception) {
            if ($staging instanceof BuildStagingArea) {
                $staging->discard();
            }

            if ($json) {
                $this->writeJson($output, [
                    'status' => 'error',
                    'code' => 'SS-INT-0001',
                    'message' => $exception->getMessage(),
                    'exit_code' => Command::FAILURE,
                ]);
            } else {
                $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
            }

            return Command::FAILURE;
        }
    }

    private function resolveGitTimeout(mixed $cliValue): int
    {
        if ($cliValue !== null && trim((string) $cliValue) !== '') {
            $value = (int) $cliValue;
            if ($value < 1) {
                throw new SourceSlateException('SS-CFG-0011', '--git-timeout must be greater than zero.', 11);
            }
            return $value;
        }

        $environment = getenv('SOURCESLATE_GIT_TIMEOUT');
        if (is_string($environment) && trim($environment) !== '') {
            $value = (int) $environment;
            if ($value < 1) {
                throw new SourceSlateException('SS-CFG-0011', 'SOURCESLATE_GIT_TIMEOUT must be greater than zero.', 11);
            }
            return $value;
        }

        return 60;
    }

    private function emitDryRun(OutputInterface $output, bool $json, object $workspace, ?string $configPath, object $config, string $outputDirectory): void
    {
        $plan = [
            'status' => 'success',
            'mode' => 'dry-run',
            'source' => [
                'type' => $workspace->remote ? 'git' : 'local',
                'root' => $workspace->root,
                'repository' => $workspace->repository,
                'branch' => $workspace->branch,
                'requested_ref' => $workspace->requestedRef,
                'resolved_commit' => $workspace->resolvedCommit,
                'git_state' => $workspace->gitState,
                'cache_status' => $workspace->cacheStatus,
                'offline' => $workspace->offline,
            ],
            'configuration' => [
                'path' => $configPath,
                'project_name' => $config->projectName,
                'source_paths' => $config->sourcePaths,
                'exclude_paths' => $config->excludePaths,
            ],
            'output' => ['path' => $outputDirectory],
        ];

        if ($json) {
            $this->writeJson($output, $plan);
            return;
        }

        $output->writeln('<info>SourceSlate dry run</info>');
        $output->writeln(sprintf('Source: %s', $workspace->root));
        if ($workspace->repository !== null) {
            $output->writeln(sprintf('Repository: %s', $workspace->repository));
        }
        if ($workspace->resolvedCommit !== null) {
            $output->writeln(sprintf('Commit: %s', $workspace->resolvedCommit));
        }
        if ($workspace->cacheStatus !== null) {
            $output->writeln(sprintf('Cache: %s', $workspace->cacheStatus));
        }
        $output->writeln(sprintf('Project: %s', $config->projectName));
        $output->writeln(sprintf('Output: %s', $outputDirectory));
    }

    private function writeJson(OutputInterface $output, array $data): void
    {
        $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function absoluteOutputPath(string $path): string
    {
        if ($path === '') {
            throw new OutputException('SS-OUT-0040', '--output cannot be empty.', 40);
        }

        if (preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path) === 1) {
            return rtrim($path, '\\/');
        }

        $cwd = getcwd();
        if ($cwd === false) {
            throw new OutputException('SS-OUT-0040', 'Unable to resolve the current working directory.', 40);
        }

        return rtrim($cwd, '\\/') . DIRECTORY_SEPARATOR . $path;
    }

    private function assertRemoteOutputSafety(string $outputDirectory, string $sourceRoot, string $cacheRoot): void
    {
        $normalizedOutput = $this->normalizePath($outputDirectory);
        $normalizedSource = $this->normalizePath($sourceRoot);
        $normalizedCache = $this->normalizePath($cacheRoot);

        if ($this->isSameOrDescendant($normalizedOutput, $normalizedSource)) {
            throw new OutputException('SS-OUT-0041', 'Remote --output cannot be inside the cached source workspace.', 41);
        }

        if ($this->isSameOrDescendant($normalizedOutput, $normalizedCache)) {
            throw new OutputException('SS-OUT-0041', 'Remote --output cannot be inside the SourceSlate Git cache.', 41);
        }
    }

    private function normalizePath(string $path): string
    {
        $resolved = realpath($path);
        $value = $resolved !== false ? $resolved : $path;
        $value = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $value);

        return rtrim($value, DIRECTORY_SEPARATOR);
    }

    private function isSameOrDescendant(string $candidate, string $parent): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $candidate = strtolower($candidate);
            $parent = strtolower($parent);
        }

        return $candidate === $parent
            || str_starts_with($candidate . DIRECTORY_SEPARATOR, $parent . DIRECTORY_SEPARATOR);
    }
}
