<?php

declare(strict_types=1);

namespace SourceSlate\Command;

use SourceSlate\Configuration\ConfigurationLoader;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'doctor', description: 'Check the SourceSlate runtime and project environment.')]
final class DoctorCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('project', InputArgument::OPTIONAL, 'Local project root to validate.', '.')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Explicit SourceSlate YAML configuration file.')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output path to validate instead of the configured path.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable diagnostics.');
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

        $projectInput = (string) $input->getArgument('project');
        $projectRoot = realpath($projectInput);
        $projectValid = $projectRoot !== false && is_dir($projectRoot) && is_readable($projectRoot);
        $checks[] = $this->check('project-root', $projectValid, $projectValid ? $projectRoot : $projectInput, 'SS-DOC-1010');

        if ($projectValid) {
            $configPath = $input->getOption('config') !== null ? (string) $input->getOption('config') : null;
            try {
                $configuration = (new ConfigurationLoader())->load($projectRoot, $configPath);
                $checks[] = $this->check('configuration', true, $configPath ?? 'resolved by precedence', 'SS-DOC-1011');

                foreach ($configuration->sourcePaths as $sourcePath) {
                    $absoluteSource = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourcePath);
                    $sourceReady = is_dir($absoluteSource) && is_readable($absoluteSource);
                    $checks[] = $this->check('source:' . $sourcePath, $sourceReady, $absoluteSource, 'SS-DOC-1012');
                }

                $outputPath = $input->getOption('output') !== null
                    ? $this->absolutePath((string) $input->getOption('output'))
                    : $projectRoot . DIRECTORY_SEPARATOR . $configuration->outputPath;
                $checks[] = $this->check('output', $this->pathIsWritableOrCreatable($outputPath), $outputPath, 'SS-DOC-1013');
                $checks[] = $this->check('output-symlink', !$this->hasSymlinkComponent($outputPath), $outputPath, 'SS-DOC-1014');
            } catch (\Throwable $exception) {
                $checks[] = $this->check('configuration', false, $exception->getMessage(), 'SS-DOC-1011');
            }
        }

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
                '%s %-24s %s%s',
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

    private function hasSymlinkComponent(string $path): bool
    {
        $absolute = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $prefix = '';
        if (preg_match('/^[A-Za-z]:\\\\/', $absolute) === 1) {
            $prefix = substr($absolute, 0, 3);
            $absolute = substr($absolute, 3);
        } elseif (str_starts_with($absolute, DIRECTORY_SEPARATOR)) {
            $prefix = DIRECTORY_SEPARATOR;
            $absolute = ltrim($absolute, DIRECTORY_SEPARATOR);
        }

        $current = rtrim($prefix, DIRECTORY_SEPARATOR);
        foreach (array_filter(explode(DIRECTORY_SEPARATOR, $absolute), static fn (string $part): bool => $part !== '') as $part) {
            $current = $current === '' || $current === DIRECTORY_SEPARATOR
                ? $current . $part
                : $current . DIRECTORY_SEPARATOR . $part;
            if (is_link($current)) {
                return true;
            }
            if (!file_exists($current)) {
                break;
            }
        }

        return false;
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path) === 1) {
            return $path;
        }
        $cwd = getcwd();
        return ($cwd !== false ? rtrim($cwd, '\\/') : '.') . DIRECTORY_SEPARATOR . $path;
    }
}
