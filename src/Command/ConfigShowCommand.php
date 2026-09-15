<?php

declare(strict_types=1);

namespace SourceSlate\Command;

use SourceSlate\Configuration\ConfigurationLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'config:show', description: 'Show the fully resolved SourceSlate configuration.')]
final class ConfigShowCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('project', InputArgument::OPTIONAL, 'Project root.', '.')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Explicit SourceSlate YAML configuration file.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $project = (string) $input->getArgument('project');
            $configPath = $input->getOption('config') !== null ? (string) $input->getOption('config') : null;
            $configuration = (new ConfigurationLoader())->load($project, $configPath);

            $data = [
                'project' => ['name' => $configuration->projectName],
                'source' => [
                    'paths' => $configuration->sourcePaths,
                    'exclude' => $configuration->excludePaths,
                ],
                'output' => ['path' => $configuration->outputPath],
                'source_headers' => ['update' => $configuration->updateSource],
            ];

            if ((bool) $input->getOption('json')) {
                $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } else {
                $output->writeln('project.name: ' . $configuration->projectName);
                $output->writeln('source.paths: ' . implode(', ', $configuration->sourcePaths));
                $output->writeln('source.exclude: ' . implode(', ', $configuration->excludePaths));
                $output->writeln('output.path: ' . $configuration->outputPath);
                $output->writeln('source_headers.update: ' . ($configuration->updateSource ? 'true' : 'false'));
            }

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            if ((bool) $input->getOption('json')) {
                $output->writeln(json_encode([
                    'status' => 'error',
                    'code' => 'SS-CFG-0001',
                    'message' => $exception->getMessage(),
                    'exit_code' => 11,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } else {
                $output->writeln('<error>SS-CFG-0001: ' . $exception->getMessage() . '</error>');
            }
            return 11;
        }
    }
}
