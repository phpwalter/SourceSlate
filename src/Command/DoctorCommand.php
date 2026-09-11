<?php

declare(strict_types=1);

namespace SourceSlate\Command;

use SourceSlate\Source\Git\GitClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'doctor', description: 'Check the SourceSlate runtime environment.')]
final class DoctorCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $checks = [
            ['PHP >= 8.3', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION],
            ['ext-json', extension_loaded('json'), extension_loaded('json') ? 'loaded' : 'missing'],
            ['ext-mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'loaded' : 'missing'],
            ['proc_open', function_exists('proc_open'), function_exists('proc_open') ? 'available' : 'disabled'],
        ];

        $git = new GitClient();
        $gitAvailable = $git->isAvailable();
        $checks[] = ['git', $gitAvailable, $gitAvailable ? $git->run(['--version']) : 'not found on PATH'];

        $ok = true;
        foreach ($checks as [$name, $passed, $detail]) {
            $ok = $ok && $passed;
            $output->writeln(sprintf('%s %-16s %s', $passed ? '[OK]' : '[FAIL]', $name, $detail));
        }

        return $ok ? Command::SUCCESS : Command::FAILURE;
    }
}
