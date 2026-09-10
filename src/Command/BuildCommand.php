<?php

/**
 * @file BuildCommand.php
 * @path src/Command/BuildCommand.php
 * @version 1.1.0
 * @date 2026-09-10
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

use SourceSlate\Configuration\ConfigurationLoader;
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
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Remote Git branch to document.')
            ->addOption('ref', null, InputOption::VALUE_REQUIRED, 'Remote Git tag, branch ref, or commit SHA to document.')
            ->addOption('source-path', null, InputOption::VALUE_REQUIRED, 'Subdirectory within the resolved source workspace.')
            ->addOption('refresh', null, InputOption::VALUE_NONE, 'Force remote revalidation when supported.')
            ->addOption('offline', null, InputOption::VALUE_NONE, 'Use only the persistent Git cache; never contact the remote.')
            ->addOption('recurse-submodules', null, InputOption::VALUE_NONE, 'Fetch submodules when remote Git support enables them.')
            ->addOption('update-source', null, InputOption::VALUE_NONE, 'Update source headers with @sourceslate links when supported.')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Run documentation consistency checks when supported.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $request = new SourceRequest(
                source: (string) $input->getArgument('project'),
                output: $input->getOption('output') !== null ? (string) $input->getOption('output') : null,
                branch: $input->getOption('branch') !== null ? (string) $input->getOption('branch') : null,
                ref: $input->getOption('ref') !== null ? (string) $input->getOption('ref') : null,
                sourcePath: $input->getOption('source-path') !== null ? (string) $input->getOption('source-path') : null,
                refresh: (bool) $input->getOption('refresh'),
                offline: (bool) $input->getOption('offline'),
                recurseSubmodules: (bool) $input->getOption('recurse-submodules'),
            );

            $resolver = new SourceResolver(new GitSourceProvider(new GitClient(), GitCache::default()));
            $workspace = $resolver->resolve($request);
            $root = $workspace->root;

            $config = (new ConfigurationLoader())->load(
                $root,
                $input->getOption('config') !== null ? (string) $input->getOption('config') : null,
            );

            if ((bool) $input->getOption('update-source')) {
                if ($workspace->remote) {
                    throw new \InvalidArgumentException('--update-source is not permitted for remote Git sources.');
                }
                $output->writeln('<comment>Source-header mutation is reserved by the 1.0 contract but is not enabled in this foundation build.</comment>');
            }

            if ((bool) $input->getOption('check')) {
                $output->writeln('<comment>Validation/check mode is reserved for the validation subsystem planned after the core generator.</comment>');
            }

            $project = (new PhpSourceParser())->parse($root, $config);
            $outputDirectory = $request->output !== null
                ? $this->absoluteOutputPath($request->output)
                : $root . DIRECTORY_SEPARATOR . $config->outputPath;

            (new HtmlRenderer())->render($project, $outputDirectory);

            if ($workspace->remote) {
                $output->writeln(sprintf('<info>Resolved %s at %s.</info>', $workspace->repository, $workspace->resolvedCommit));
            }

            $output->writeln(sprintf(
                '<info>SourceSlate generated documentation for %d PHP file(s) in %s.</info>',
                count($project->files),
                $outputDirectory,
            ));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
            return Command::FAILURE;
        }
    }

    private function absoluteOutputPath(string $path): string
    {
        if ($path === '') {
            throw new \InvalidArgumentException('--output cannot be empty.');
        }

        if (preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path) === 1) {
            return rtrim($path, '\\/');
        }

        $cwd = getcwd();
        if ($cwd === false) {
            throw new \RuntimeException('Unable to resolve the current working directory.');
        }

        return rtrim($cwd, '\\/') . DIRECTORY_SEPARATOR . $path;
    }
}
