<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Codeception\Test\Unit;
use ItPack\CommitIt\Application\ObservedRepository;
use ItPack\CommitIt\Application\RepositoryOpener;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * @covers \ItPack\CommitIt\Application\RepositoryOpener
 */
final class RepositoryOpenerTest extends Unit
{
    public function testOpenWithObserverReturnsObservedRepository(): void
    {
        $factory = new FakeFactory();
        $observer = new RecordingObserver();
        $opener = new RepositoryOpener($factory, $observer);

        $repository = $opener->open(RepositoryPath::from(sys_get_temp_dir()));

        self::assertInstanceOf(ObservedRepository::class, $repository);

        $repository->commit(CommitMessage::fromParts('Subject'));

        self::assertSame(['start:commit', 'success:commit'], $observer->sequence());
    }

    public function testOpenWithoutObserverReturnsPlainRepository(): void
    {
        $factory = new FakeFactory();
        $opener = new RepositoryOpener($factory);

        $repository = $opener->open(RepositoryPath::from(sys_get_temp_dir()));

        self::assertInstanceOf(FakeRepository::class, $repository);
    }

    public function testInitAndCloneWireObserver(): void
    {
        $factory = new FakeFactory();
        $observer = new RecordingObserver();
        $opener = new RepositoryOpener($factory, $observer);

        $opened = $opener->init(RepositoryPath::from(sys_get_temp_dir()));
        $cloned = $opener->cloneRepository('https://example.com/repo.git', RepositoryPath::from(sys_get_temp_dir()));

        self::assertInstanceOf(ObservedRepository::class, $opened);
        self::assertInstanceOf(ObservedRepository::class, $cloned);

        $opened->stageAll();
        $cloned->fetch();

        self::assertSame(
            ['start:stageAll', 'success:stageAll', 'start:fetch', 'success:fetch'],
            $observer->sequence(),
        );
    }
}
