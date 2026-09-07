<?php

declare(strict_types=1);

namespace Tests\Unit\Behavior\Domain;

use Codeception\Test\Unit;
use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Behavior\Domain\RepositoryBehaviorHarness;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use Tests\Support\Helper\GitRepoFactory;

/**
 * @covers \ItPack\CommitIt\Behavior\Domain\RepositoryBehaviorHarness
 * @covers \ItPack\CommitIt\Behavior\Domain\HarnessResult
 */
final class RepositoryBehaviorHarnessTest extends Unit
{
    public function testHarnessPassesAgainstGitAdapter(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-harness');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'hello');
            $repository = new GitPhpRepositoryFactory()->open(RepositoryPath::from($repo));
            $repository->stage('a.txt');
            $repository->commit(\ItPack\CommitIt\Domain\Value\CommitMessage::fromParts('initial'));

            $result = RepositoryBehaviorHarness::verify($repository)->run();

            self::assertTrue($result->passed(), implode("\n", iterator_to_array($result)));
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }
}
