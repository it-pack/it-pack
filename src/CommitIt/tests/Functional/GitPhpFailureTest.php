<?php

declare(strict_types=1);

namespace Tests\Functional;

use Codeception\Test\Unit;
use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Domain\Exception\RepositoryNotFoundException;
use ItPack\CommitIt\Domain\Exception\VcsOperationException;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use Tests\Support\Helper\GitRepoFactory;

/**
 * Failure-injection and concurrency isolation against real git repositories.
 *
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpRepository
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory
 * @covers \ItPack\CommitIt\Adapter\Git\GitExceptionMap
 */
final class GitPhpFailureTest extends Unit
{
    private function open(string $path): Repository
    {
        return new GitPhpRepositoryFactory()->open(RepositoryPath::from($path));
    }

    public function testOpenMissingPathThrowsRepositoryNotFound(): void
    {
        $missing = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'commitit-missing-' . bin2hex(random_bytes(8));

        $this->expectException(RepositoryNotFoundException::class);
        $this->open($missing);
    }

    public function testOpenNonGitDirectoryThrowsRepositoryNotFound(): void
    {
        $dir = GitRepoFactory::createTempDir('commitit-nongit');

        try {
            $this->expectException(RepositoryNotFoundException::class);
            $this->open($dir);
        } finally {
            GitRepoFactory::tearDown($dir);
        }
    }

    public function testDirtyWorkingTreeBlocksCheckout(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-dirty');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'f.txt', 'v1');
            $repository->stage('f.txt');
            $repository->commit(CommitMessage::fromParts('base'));

            $repository->createBranch(BranchName::from('feature'));
            $repository->checkoutBranch(BranchName::from('feature'));
            file_put_contents($repo . DIRECTORY_SEPARATOR . 'f.txt', 'v2');
            $repository->stage('f.txt');
            $repository->commit(CommitMessage::fromParts('feature change'));

            $repository->checkoutBranch(BranchName::from('main'));
            file_put_contents($repo . DIRECTORY_SEPARATOR . 'f.txt', 'v3');

            // Live git (via czproject/git-php) hides stderr, so the adapter maps
            // the conflict to a generic VcsOperationException rather than the
            // specific DirtyWorkingTreeException — the operation is still blocked
            // and never silently succeeds. The DirtyWorkingTreeException branch
            // itself is proven by the MemoryRunner-scripted unit tests.
            $this->expectException(VcsOperationException::class);
            $repository->checkoutBranch(BranchName::from('feature'));
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testRepeatedOperationsDoNotLeakState(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-conc');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            for ($i = 0; $i < 15; $i++) {
                file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', (string) $i);
                $repository->stage('a.txt');
                $repository->commit(CommitMessage::fromParts('iteration-' . $i));

                $last = $repository->getLastCommit();
                self::assertNotNull($last);
                self::assertSame('iteration-' . $i, $last->subject);
                self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $last->id->__toString());
            }
        } finally {
            GitRepoFactory::tearDown($repo);
        }

        for ($i = 0; $i < 10; $i++) {
            $dir = GitRepoFactory::createTempDir('commitit-conc');

            try {
                GitRepoFactory::initWorkingRepo($dir, 'main');
                $repository = $this->open($dir);

                file_put_contents($dir . DIRECTORY_SEPARATOR . 'a.txt', 'x');
                $repository->stage('a.txt');
                $id = $repository->commit(CommitMessage::fromParts('commit-' . $i));

                self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $id->__toString());
            } finally {
                GitRepoFactory::tearDown($dir);
            }
        }
    }
}
