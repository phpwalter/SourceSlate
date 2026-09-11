<?php

declare(strict_types=1);

namespace SourceSlate\Source\Git;

use SourceSlate\Exception\GitException;

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
            throw new GitException('SS-GIT-0020', 'Unable to start git process.', 20);
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $message = trim((string) $stderr);
            [$code, $mappedExitCode] = $this->classifyFailure($message);

            throw new GitException($code, $message !== '' ? $message : 'Git command failed.', $mappedExitCode);
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

    /** @return array{0:string,1:int} */
    private function classifyFailure(string $message): array
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'authentication failed') || str_contains($lower, 'permission denied (publickey)')) {
            return ['SS-GIT-0022', 22];
        }

        if (str_contains($lower, 'repository not found') || str_contains($lower, 'could not read from remote repository')) {
            return ['SS-GIT-0021', 21];
        }

        if (str_contains($lower, 'unknown revision') || str_contains($lower, 'ambiguous argument') || str_contains($lower, 'not a valid object name')) {
            return ['SS-GIT-0023', 23];
        }

        return ['SS-GIT-0025', 25];
    }
}
