<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use ItPack\AsyncIt\Contract\Exception\BulkheadFullException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Contract\Exception\BulkheadFullException
 */
final class BulkheadFullExceptionTest extends TestCase
{
    public function testDefaultMessageContainsCapacity(): void
    {
        $exception = new BulkheadFullException(5);

        self::assertSame('Bulkhead is at capacity (5).', $exception->getMessage());
    }

    public function testCustomMessageOverridesDefault(): void
    {
        $exception = new BulkheadFullException(3, 'custom message');

        self::assertSame('custom message', $exception->getMessage());
    }

    public function testCapacityReturnsConstructorValue(): void
    {
        $exception = new BulkheadFullException(7);

        self::assertSame(7, $exception->capacity());
    }

    public function testEmptyStringUsesDefaultMessage(): void
    {
        $exception = new BulkheadFullException(10, '');

        self::assertSame('Bulkhead is at capacity (10).', $exception->getMessage());
    }

    public function testImplementsAsyncItExceptionInterface(): void
    {
        $exception = new BulkheadFullException(1);

        self::assertInstanceOf(AsyncItExceptionInterface::class, $exception);
    }
}
