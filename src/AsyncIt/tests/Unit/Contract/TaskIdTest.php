<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\TaskId;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Contract\TaskId
 */
final class TaskIdTest extends TestCase
{
    public function testRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TaskId('');
    }

    public function testStoresAndExposesValue(): void
    {
        $id = new TaskId('task-42');

        self::assertSame('task-42', $id->value);
    }

    public function testSerializesToString(): void
    {
        $id = new TaskId('task-abc');

        self::assertSame('task-abc', (string) $id);
    }
}
