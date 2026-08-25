<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use Codeception\Test\Unit;
use ItPack\CommitIt\Contract\BranchPort;
use ItPack\CommitIt\Contract\CommitPort;
use ItPack\CommitIt\Contract\RemotePort;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Contract\TagPort;
use ItPack\CommitIt\Contract\WorkingTree;
use Tests\Unit\Application\FakeRepository;

/**
 * @covers \ItPack\CommitIt\Contract\Repository
 * @covers \ItPack\CommitIt\Contract\CommitPort
 * @covers \ItPack\CommitIt\Contract\BranchPort
 * @covers \ItPack\CommitIt\Contract\TagPort
 * @covers \ItPack\CommitIt\Contract\RemotePort
 */
final class RepositoryPortContractTest extends Unit
{
    public function testRepositoryUnionSatisfiesEveryFocusedPort(): void
    {
        $repository = new FakeRepository();

        self::assertInstanceOf(Repository::class, $repository);
        self::assertInstanceOf(CommitPort::class, $repository);
        self::assertInstanceOf(BranchPort::class, $repository);
        self::assertInstanceOf(TagPort::class, $repository);
        self::assertInstanceOf(RemotePort::class, $repository);
    }

    public function testWorkingTreeIsComposedNotFlattened(): void
    {
        $repository = new FakeRepository();

        // The working tree is reached through getWorkingTree(); the repository is
        // intentionally NOT itself a WorkingTree operator (see ADR-006).
        self::assertInstanceOf(WorkingTree::class, $repository->getWorkingTree());
    }
}
