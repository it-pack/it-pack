<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Scheduler;

use Fiber;
use ItPack\AsyncIt\Application\Scheduler\TaskRecord;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\StreamPollHandle;
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

    public function testSetAndGetMetadata(): void
    {
        $record = $this->createRecord();
        $value = new class () {};

        $record->setMetadata('key', $value);

        self::assertTrue($record->hasMetadata('key'));
        self::assertSame($value, $record->getMetadata('key'));
    }

    public function testGetMissingMetadataReturnsNull(): void
    {
        $record = $this->createRecord();

        self::assertNull($record->getMetadata('missing'));
        self::assertFalse($record->hasMetadata('missing'));
    }

    public function testRemoveMetadata(): void
    {
        $record = $this->createRecord();
        $value = new class () {};

        $record->setMetadata('key', $value);
        $record->removeMetadata('key');

        self::assertFalse($record->hasMetadata('key'));
        self::assertNull($record->getMetadata('key'));
    }

    public function testMarkDoneClearsMetadata(): void
    {
        $record = $this->createRecord();
        $value = new class () {};

        $record->setMetadata('key', $value);
        $record->markDone();

        self::assertFalse($record->hasMetadata('key'));
        self::assertNull($record->getMetadata('key'));
    }

    private function createRecord(): TaskRecord
    {
        return new TaskRecord(
            $this->createMock(TaskInterface::class),
            new Fiber(static function (): void {
            }),
        );
    }

    private function createHandle(string $id): StreamPollHandleInterface
    {
        return new StreamPollHandle($id);
    }
}
