<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use RuntimeException;

/**
 * Creates and destroys real temporary git repositories for functional and
 * integration tests. Uses proc_open with an argument vector (no shell), so
 * inputs are not interpreted by a shell and cannot inject commands. Never uses
 * elevated privileges (no sudo).
 */
final class GitRepoFactory
{
    private function __construct()
    {
    }

    public static function createTempDir(string $prefix = 'commitit'): string
    {
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $prefix
            . '_'
            . bin2hex(random_bytes(8));

        if (!mkdir($path, 0o755, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create temp dir "%s".', $path));
        }

        return $path;
    }

    public static function initBareRepo(string $path): void
    {
        self::runGit(dirname($path), 'init', '--bare', $path);
    }

    public static function initWorkingRepo(string $path, string $branch = 'main'): void
    {
        if (!is_dir($path) && !mkdir($path, 0o755, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create repo dir "%s".', $path));
        }

        self::runGit($path, 'init', '--initial-branch', $branch);
        self::runGit($path, 'config', 'user.email', 'commitit@example.com');
        self::runGit($path, 'config', 'user.name', 'CommitIt Test');
    }

    public static function seedFile(string $repoPath, string $fileName, string $content): void
    {
        $target = $repoPath . DIRECTORY_SEPARATOR . $fileName;

        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException(sprintf('Unable to write file "%s".', $target));
        }

        self::runGit($repoPath, 'add', $fileName);
        self::runGit($repoPath, 'commit', '--message', 'seed: ' . $fileName);
    }

    public static function cloneRepo(string $source, string $target): void
    {
        self::runGit(dirname($target), 'clone', $source, $target);
    }

    public static function tearDown(string ...$paths): void
    {
        foreach ($paths as $path) {
            self::removeDirectory($path);
        }
    }

    public static function runGit(string $cwd, string ...$args): string
    {
        $command = ['git', ...$args];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($command, $descriptors, $pipes, $cwd);

        if ($proc === false) {
            throw new RuntimeException('Unable to start git process.');
        }

        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($proc);

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'git %s failed (exit %d): %s',
                implode(' ', $args),
                $exitCode,
                trim($stderr),
            ));
        }

        return $stdout;
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($full)) {
                self::removeDirectory($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }
}
