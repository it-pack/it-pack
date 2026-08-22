<?php

declare(strict_types=1);

namespace Tests\Unit\SDK;

use Codeception\Test\Unit;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Domain\Option\CloneOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\SDK\CommitIt;
use Tests\Unit\Application\FakeFactory;
use Tests\Unit\Application\FakeRepository;
use Tests\Unit\Application\RecordingObserver;

/**
 * @covers \ItPack\CommitIt\SDK\CommitIt
 */
final class CommitItTest extends Unit
{
    private function path(): RepositoryPath
    {
        return RepositoryPath::from(sys_get_temp_dir());
    }

    public function testOpenDelegatesToFactoryAndReturnsRepository(): void
    {
        $repository = CommitIt::open($this->path(), new FakeFactory());

        self::assertInstanceOf(Repository::class, $repository);
        self::assertInstanceOf(FakeRepository::class, $repository);
    }

    public function testInitDelegatesToFactoryAndReturnsRepository(): void
    {
        $repository = CommitIt::init($this->path(), new FakeFactory(), InitOptions::default());

        self::assertInstanceOf(Repository::class, $repository);
        self::assertInstanceOf(FakeRepository::class, $repository);
    }

    public function testCloneDelegatesToFactoryAndReturnsRepository(): void
    {
        $repository = CommitIt::clone(
            'https://example.com/repo.git',
            $this->path(),
            new FakeFactory(),
            CloneOptions::default(),
        );

        self::assertInstanceOf(Repository::class, $repository);
        self::assertInstanceOf(FakeRepository::class, $repository);
    }

    public function testOpenWithObserverObservesReturnedRepository(): void
    {
        $observer = new RecordingObserver();

        $repository = CommitIt::open($this->path(), new FakeFactory(), $observer);

        $repository->commit(CommitMessage::fromParts('Subject'));

        self::assertSame(['start:commit', 'success:commit'], $observer->sequence());
    }

    public function testFacadeDoesNotCacheTheSuppliedFactory(): void
    {
        $first = CommitIt::open($this->path(), new FakeFactory());
        $second = CommitIt::open($this->path(), new FakeFactory());

        self::assertNotSame($first, $second);
    }
}
