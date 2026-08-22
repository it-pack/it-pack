<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Git;

use Codeception\Test\Unit;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\Runners\MemoryRunner;
use ItPack\CommitIt\Domain\Exception\VcsOperationException;

/**
 * Drives the adapter with a scripted {@see MemoryRunner} so the reachable branch
 * of {@see \ItPack\CommitIt\Adapter\Git\GitExceptionMap} is exercised at the
 * repository boundary, and the original {@see GitException} is preserved as the
 * `previous` throwable.
 *
 * Note: czproject/git-php throws `GitException("Command 'git ...' failed
 * (exit-code N)")` and does NOT surface git's stderr in the message, so the
 * specific `InvalidReferenceNameException` / `DirtyWorkingTreeException` /
 * `RepositoryNotFoundException` branches cannot be reached from the live
 * adapter — they are covered directly in {@see GitExceptionMapTest} with
 * hand-built {@see GitException} instances carrying the matching text.
 *
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpRepository
 * @covers \ItPack\CommitIt\Adapter\Git\GitExceptionMap
 */
final class GitPhpExceptionMappingTest extends Unit
{
    use GitAdapterMemoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRepoDir();
    }

    protected function tearDown(): void
    {
        $this->tearDownRepoDir();
        parent::tearDown();
    }

    public function testGenericGitExceptionMapsToVcsOperationPreservingPrevious(): void
    {
        $repository = $this->buildRepository(function (MemoryRunner $runner): void {
            $runner->setResult(['add', '--end-of-options', 'file.txt'], [], '', 'some unexpected failure', 1);
        });

        try {
            $repository->stage('file.txt');
            self::fail('Expected a VcsOperationException.');
        } catch (VcsOperationException $e) {
            self::assertInstanceOf(GitException::class, $e->getPrevious());
        }
    }
}
