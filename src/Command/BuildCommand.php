<?php

/**
 * @file BuildCommand.php
 * @path src/Command/BuildCommand.php
 * @version 1.0.0
 * @date 2026-09-02
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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Builds static documentation for a PHP project.
 *
 * Source mutation remains opt-in. The initial command recognizes
 * --update-source but deliberately does not modify source until the dedicated
 * header writer is implemented, preventing accidental partial mutation.
 */
#[AsCommand(name: 'build', description: 'Build static SourceSlate documentation.')]
final class BuildCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('project', InputArgument::OPTIONAL, 'Project root to document.', '.')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Explicit SourceSlate YAML configuration file.')
            ->addOption('update-source', null, InputOption::VALUE_NONE, 'Update source headers with @sourceslate links when supported.')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Run documentation consistency checks when supported.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = (string) $input->getArgument('project');
        $root = realpath($projectRoot);
        if ($root === false) {
            $output->writeln(sprintf('<error>Project root does not exist: %s</error>', $projectRoot));

            return Command::FAILURE;
        }

        $config = (new ConfigurationLoader())->load(
            $root,
            $input->getOption('config') !== null ? (string) $input->getOption('config') : null,
        );

        if ((bool) $input->getOption('update-source')) {
            $output->writeln('<comment>Source-header mutation is reserved by the 1.0 contract but is not enabled in this foundation build.</comment>');
        }

        if ((bool) $input->getOption('check')) {
            $output->writeln('<comment>Validation/check mode is reserved for the validation subsystem planned after the core generator.</comment>');
        }

        $project = (new PhpSourceParser())->parse($root, $config);
        $outputDirectory = $root . DIRECTORY_SEPARATOR . $config->outputPath;
        (new HtmlRenderer())->render($project, $outputDirectory);

        $output->writeln(sprintf(
            '<info>SourceSlate generated documentation for %d PHP file(s) in %s.</info>',
            count($project->files),
            $outputDirectory,
        ));

        return Command::SUCCESS;
    }
}
