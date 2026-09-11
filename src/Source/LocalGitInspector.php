<?php

declare(strict_types=1);

namespace SourceSlate\Source;

use SourceSlate\Source\Git\GitClient;

final readonly class LocalGitInspector
{
    public function __construct(private GitClient $git)
    {
    }

    public function inspect(string $root): array
    {
        try {
            $inside = $this->git->run(['rev-parse', '--is-inside-work-tree'], $root);
            if ($inside !== 'true') {
                return ['state' => 'not-a-git-repository'];
            }
        } catch (\Throwable) {
            return ['state' => 'not-a-git-repository'];
        }

        $branch = null;
        try {
            $branch = $this->git->run(['branch', '--show-current'], $root);
            if ($branch === '') {
                $branch = null;
            }
        } catch (\Throwable) {
            $branch = null;
        }

        $remote = null;
        try {
            $remote = $this->git->run(['remote', 'get-url', 'origin'], $root);
            if ($remote === '') {
                $remote = null;
            }
        } catch (\Throwable) {
            $remote = null;
        }

        $commit = $this->git->run(['rev-parse', 'HEAD'], $root);
        $dirty = $this->git->run(['status', '--porcelain'], $root) !== '';

        return [
            'state' => $dirty ? 'dirty' : 'clean',
            'branch' => $branch,
            'repository' => $remote,
            'commit' => $commit,
        ];
    }
}
