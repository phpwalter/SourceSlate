<?php

declare(strict_types=1);

namespace SourceSlate\Tests\Source\Git;

use PHPUnit\Framework\TestCase;
use SourceSlate\Source\Git\GitCache;
use SourceSlate\Source\Git\GitClient;
use SourceSlate\Source\Git\GitSourceProvider;
use SourceSlate\Source\SourceRequest;

final class GitSourceProviderSubmoduleTest extends TestCase
{
    public function testRecursiveSubmoduleCheckoutPopulatesSubmoduleContent(): void
    {
        $root = $this->temporaryDirectory();
        $git = new GitClient(30);
        $previousAllowProtocol = getenv('GIT_ALLOW_PROTOCOL');

        try {
            $subWork = $root . DIRECTORY_SEPARATOR . 'sub-work';
            $subRemote = $root . DIRECTORY_SEPARATOR . 'sub.git';
            mkdir($subWork, 0777, true);
            file_put_contents($subWork . DIRECTORY_SEPARATOR . 'Library.php', "<?php\nfinal class Library {}\n");
            $git->run(['init', '-b', 'main'], $subWork);
            $git->run(['add', '.'], $subWork);
            $git->run(['-c', 'user.name=SourceSlate Tests', '-c', 'user.email=sourceslate@example.test', 'commit', '-m', 'submodule'], $subWork);
            $git->run(['init', '--bare', $subRemote]);
            $git->run(['remote', 'add', 'origin', $this->fileUrl($subRemote)], $subWork);
            $git->run(['push', '-u', 'origin', 'main'], $subWork);
            $git->run(['symbolic-ref', 'HEAD', 'refs/heads/main'], $subRemote);

            $work = $root . DIRECTORY_SEPARATOR . 'work';
            $remote = $root . DIRECTORY_SEPARATOR . 'remote.git';
            mkdir($work . DIRECTORY_SEPARATOR . 'src', 0777, true);
            file_put_contents($work . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php', "<?php\nfinal class Example {}\n");
            $git->run(['init', '-b', 'main'], $work);
            $git->run(['-c', 'protocol.file.allow=always', 'submodule', 'add', $this->fileUrl($subRemote), 'modules/library'], $work);
            $git->run(['add', '.'], $work);
            $git->run(['-c', 'user.name=SourceSlate Tests', '-c', 'user.email=sourceslate@example.test', 'commit', '-m', 'with submodule'], $work);
            $git->run(['init', '--bare', $remote]);
            $git->run(['remote', 'add', 'origin', $this->fileUrl($remote)], $work);
            $git->run(['push', '-u', 'origin', 'main'], $work);
            $git->run(['symbolic-ref', 'HEAD', 'refs/heads/main'], $remote);

            putenv('GIT_ALLOW_PROTOCOL=file');
            $provider = new GitSourceProvider(new GitClient(30), new GitCache($root . DIRECTORY_SEPARATOR . 'cache'));
            $workspace = $provider->resolve(new SourceRequest(
                source: $this->fileUrl($remote),
                output: $root . DIRECTORY_SEPARATOR . 'docs',
                branch: 'main',
                recurseSubmodules: true,
            ));

            self::assertFileExists($workspace->root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Library.php');
        } finally {
            if ($previousAllowProtocol === false) {
                putenv('GIT_ALLOW_PROTOCOL');
            } else {
                putenv('GIT_ALLOW_PROTOCOL=' . $previousAllowProtocol);
            }
            $this->removeDirectory($root);
        }
    }

    private function fileUrl(string $path): string
    {
        $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        return preg_match('/^[A-Za-z]:\//', $normalized) === 1 ? 'file:///' . $normalized : 'file://' . $normalized;
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sourceslate-submodule-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        return $root;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $child = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($child) && !is_link($child)) {
                $this->removeDirectory($child);
            } else {
                @unlink($child);
            }
        }
        @rmdir($path);
    }
}
