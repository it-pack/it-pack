<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Git;

use CzProject\GitPhp\GitRepository;
use CzProject\GitPhp\Runners\MemoryRunner;
use ItPack\CommitIt\Adapter\Git\GitPhpRepository;
use ItPack\CommitIt\Adapter\Git\GitPhpWorkingTree;
use RuntimeException;

/**
 * Shared helpers for adapter unit tests driven by `czproject/git-php`'s
 * {@see MemoryRunner}, so no real git binary is spawned.
 */
trait GitAdapterMemoryTrait
{
    protected string $repoDir = '';

    public const TEST_COMMIT_ID = '1111111111111111111111111111111111111111';

    protected function setUpRepoDir(): void
    {
        $this->repoDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'commitit_adapter_'
            . bin2hex(random_bytes(8));

        if (!mkdir($this->repoDir, 0o755, true) && !is_dir($this->repoDir)) {
            throw new RuntimeException(sprintf('Unable to create temp dir "%s".', $this->repoDir));
        }
    }

    protected function tearDownRepoDir(): void
    {
        if (!is_dir($this->repoDir)) {
            return;
        }

        $items = scandir($this->repoDir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $this->repoDir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($full)) {
                rmdir($full);
            } else {
                unlink($full);
            }
        }

        rmdir($this->repoDir);
    }

    protected function buildRepository(callable $seed): GitPhpRepository
    {
        $runner = new MemoryRunner($this->repoDir);
        $seed($runner);
        $gitRepository = new GitRepository($this->repoDir, $runner);

        return new GitPhpRepository($gitRepository);
    }

    protected function buildWorkingTree(callable $seed): GitPhpWorkingTree
    {
        $runner = new MemoryRunner($this->repoDir);
        $seed($runner);
        $gitRepository = new GitRepository($this->repoDir, $runner);

        return new GitPhpWorkingTree($gitRepository);
    }
}
