<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Codeception\Test\Unit;
use Tests\Support\Helper\GitRepoFactory;

/**
 * @covers \Tests\Support\Helper\GitRepoFactory
 */
final class GitRepoFactoryTest extends Unit
{
    public function testCreatesSeedsAndTearsDownWorkingRepo(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-helper');

        try {
            GitRepoFactory::initWorkingRepo($repo);
            GitRepoFactory::seedFile($repo, 'README.md', '# hello');

            $head = trim(GitRepoFactory::runGit($repo, 'rev-parse', 'HEAD'));

            self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $head);
        } finally {
            GitRepoFactory::tearDown($repo);
        }

        self::assertDirectoryDoesNotExist($repo);
    }

    public function testClonesBareRepository(): void
    {
        $bare = GitRepoFactory::createTempDir('commitit-bare');
        $clone = GitRepoFactory::createTempDir('commitit-clone');

        try {
            GitRepoFactory::initBareRepo($bare);
            GitRepoFactory::cloneRepo($bare, $clone);

            self::assertDirectoryExists($clone . '/.git');
        } finally {
            GitRepoFactory::tearDown($bare, $clone);
        }

        self::assertDirectoryDoesNotExist($bare);
        self::assertDirectoryDoesNotExist($clone);
    }
}
