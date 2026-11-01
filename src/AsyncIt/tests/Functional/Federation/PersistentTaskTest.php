<?php

declare(strict_types=1);

namespace Tests\Functional\Federation;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\PersistentTask;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\PersistentTask
 */
final class PersistentTaskTest extends TestCase
{
    public function testPersistentTaskIsSerializable(): void
    {
        $task = new PersistentTask(
            new TaskId('persistent-1'),
            'App\Jobs\SendEmail',
            ['email' => 'user@example.com', 'template' => 'welcome'],
            Duration::ofNanoseconds(1000),
        );

        self::assertSame('persistent-1', $task->id()->value);
        self::assertSame('App\Jobs\SendEmail', $task->operation());
        self::assertSame(['email' => 'user@example.com', 'template' => 'welcome'], $task->payload());
        self::assertSame(TaskStatus::Created, $task->status());

        $serialized = serialize($task);
        $unserialized = unserialize($serialized);

        self::assertSame($task->id()->value, $unserialized->id()->value);
        self::assertSame($task->operation(), $unserialized->operation());
        self::assertSame($task->payload(), $unserialized->payload());
    }

    public function testPersistentTaskEmptyOperationThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PersistentTask(
            new TaskId('persistent-2'),
            '',
        );
    }
}
