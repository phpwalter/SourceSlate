<?php

declare(strict_types=1);

namespace SourceSlate\Source\Git;

final class GitClient
{
    public function run(array $arguments, ?string $cwd = null): string
    {
        $command = array_merge(['git'], $arguments);
        $escaped = array_map('escapeshellarg', $command);
        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(implode(' ', $escaped), $descriptorSpec, $pipes, $cwd, ['GIT_LFS_SKIP_SMUDGE' => '1']);
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start git process.');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim($stderr) !== '' ? trim($stderr) : 'Git command failed.');
        }

        return trim((string) $stdout);
    }

    public function isAvailable(): bool
    {
        try {
            $this->run(['--version']);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
