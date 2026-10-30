<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Scheduler;

use Fiber;
use ItPack\AsyncIt\Application\Scheduler\TaskRecord;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;
use ItPack\AsyncIt\Contract\TaskInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Scheduler\TaskRecord
 */
final class TaskRecordTest extends TestCase
{
    public function testAddHandleIncreasesHandleList(): void
    {
        $record = $this->createRecord();
        $handle1 = $this->createHandle('handle-1');
        $handle2 = $this->createHandle('handle-2');

        $record->addHandle($handle1);
        $record->addHandle($handle2);

        $handles = $record->handles();

        self::assertCount(2, $handles);
        self::assertSame($handle1, $handles[0]);
        self::assertSame($handle2, $handles[1]);
    }

    public function testClearHandlesEmptiesList(): void
    {
        $record = $this->createRecord();
        $record->addHandle($this->createHandle('h1'));

        $record->clearHandles();

        self::assertSame([], $record->handles());
    }

    public function testMarkDoneSetsDoneFlag(): void
    {
        $record = $this->createRecord();

        self::assertFalse($record->isDone());

        $record->markDone();

        self::assertTrue($record->isDone());
    }

    public function testMarkDoneIsIdempotent(): void
    {
        $record = $this->createRecord();

        $record->markDone();
        $record->markDone();

        self::assertTrue($record->isDone());
    }

    private function createRecord(): TaskRecord
    {
        return new TaskRecord(
            $this->createMock(TaskInterface::class),
            new Fiber(static function (): void {
            }),
        );
    }

    private function createHandle(string $id): StreamPollHandle
    {
        return new StreamPollHandle($id);
    }
}
