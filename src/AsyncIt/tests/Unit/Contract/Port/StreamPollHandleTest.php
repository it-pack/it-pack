<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Port;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Contract\Port\StreamPollHandle
 */
final class StreamPollHandleTest extends TestCase
{
    public function testRejectsEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StreamPollHandle('');
    }

    public function testStoresIdAndSerializesToString(): void
    {
        $handle = new StreamPollHandle('poll-1');

        self::assertSame('poll-1', $handle->id);
        self::assertSame('poll-1', (string) $handle);
    }
}
