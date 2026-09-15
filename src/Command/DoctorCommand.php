<?php

declare(strict_types=1);

namespace SourceSlate\Command;

use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'doctor', description: 'Check the SourceSlate runtime environment.')]
final class DoctorCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable diagnostics.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $checks = [];
        $checks[] = $this->check('php', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION, 'SS-DOC-1001');
        foreach (['json', 'mbstring'] as $extension) {
            $loaded = extension_loaded($extension);
            $checks[] = $this->check('ext-' . $extension, $loaded, $loaded ? 'loaded' : 'missing', 'SS-DOC-1002');
        }

        $procOpen = function_exists('proc_open');
        $checks[] = $this->check('proc_open', $procOpen, $procOpen ? 'available' : 'disabled', 'SS-DOC-1003');

        $git = new GitClient();
        $gitAvailable = $git->isAvailable();
        $checks[] = $this->check('git', $gitAvailable, $gitAvailable ? $git->run(['--version']) : 'not found on PATH', 'SS-DOC-1004');

        $cache = GitCache::default();
        $cacheRoot = $cache->root();
        $checks[] = $this->check('cache', $this->pathIsWritableOrCreatable($cacheRoot), $cacheRoot, 'SS-DOC-1005');

        $lockRoot = $cacheRoot . '.locks';
        $checks[] = $this->check('locks', $this->pathIsWritableOrCreatable($lockRoot), $lockRoot, 'SS-DOC-1006');

        $debris = $this->findDebris($cacheRoot);
        $checks[] = $this->check(
            'cache-debris',
            $debris === [],
            $debris === [] ? 'none' : implode(', ', $debris),
            'SS-DOC-1007',
        );

        $ok = !in_array(false, array_column($checks, 'passed'), true);

        if ((bool) $input->getOption('json')) {
            $output->writeln(json_encode([
                'status' => $ok ? 'success' : 'error',
                'checks' => $checks,
                'exit_code' => $ok ? 0 : 1,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return $ok ? Command::SUCCESS : Command::FAILURE;
        }

        foreach ($checks as $check) {
            $output->writeln(sprintf(
                '%s %-16s %s%s',
                $check['passed'] ? '[OK]' : '[FAIL]',
                $check['name'],
                $check['detail'],
                $check['passed'] ? '' : ' [' . $check['code'] . ']',
            ));
        }

        return $ok ? Command::SUCCESS : Command::FAILURE;
    }

    /** @return array{name:string,passed:bool,detail:string,code:string} */
    private function check(string $name, bool $passed, string $detail, string $code): array
    {
        return compact('name', 'passed', 'detail', 'code');
    }

    /** @return list<string> */
    private function findDebris(string $cacheRoot): array
    {
        if (!is_dir($cacheRoot)) {
            return [];
        }

        $debris = [];
        foreach (array_diff(scandir($cacheRoot) ?: [], ['.', '..']) as $entry) {
            if (str_contains($entry, '.repair-') || str_contains($entry, '.repair-backup-')) {
                $debris[] = $entry;
            }
        }
        sort($debris);
        return $debris;
    }

    private function pathIsWritableOrCreatable(string $path): bool
    {
        if (file_exists($path)) {
            return is_dir($path) && is_writable($path);
        }

        $ancestor = dirname($path);
        while (!file_exists($ancestor)) {
            $parent = dirname($ancestor);
            if ($parent === $ancestor) {
                return false;
            }
            $ancestor = $parent;
        }

        return is_dir($ancestor) && is_writable($ancestor);
    }
}
