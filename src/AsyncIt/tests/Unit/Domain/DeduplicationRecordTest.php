<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use InvalidArgumentException;
use ItPack\AsyncIt\Domain\DeduplicationRecord;
use ItPack\AsyncIt\Domain\TaskId;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\DeduplicationRecord
 */
final class DeduplicationRecordTest extends TestCase
{
    public function testStoresAndExposesAllFields(): void
    {
        $recordedAt = hrtime(as_number: true);

        $record = new DeduplicationRecord(
            idempotencyKey: 'idem-123',
            taskId: new TaskId('task-1'),
            recordedAt: $recordedAt,
        );

        self::assertSame('idem-123', $record->idempotencyKey());
        self::assertSame('task-1', $record->taskId()->value);
        self::assertSame($recordedAt, $record->recordedAt());
    }

    public function testRejectsEmptyIdempotencyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeduplicationRecord(
            idempotencyKey: '',
            taskId: new TaskId('task-1'),
            recordedAt: hrtime(as_number: true),
        );
    }
}
