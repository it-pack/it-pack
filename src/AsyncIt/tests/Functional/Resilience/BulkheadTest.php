<?php

declare(strict_types=1);

namespace Tests\Functional\Resilience;

use DomainException;
use ItPack\AsyncIt\Contract\Exception\BulkheadFullException;
use ItPack\AsyncIt\Domain\Bulkhead;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\Bulkhead
 */
final class BulkheadTest extends TestCase
{
    public function testBulkheadAcquiresAndReleasesSlots(): void
    {
        $bulkhead = new Bulkhead(2);

        self::assertSame(2, $bulkhead->available());

        $bulkhead->tryAcquire();
        self::assertSame(1, $bulkhead->available());

        $bulkhead->tryAcquire();
        self::assertSame(0, $bulkhead->available());

        $bulkhead->release();
        self::assertSame(1, $bulkhead->available());

        $bulkhead->release();
        self::assertSame(2, $bulkhead->available());
    }

    public function testBulkheadThrowsWhenAtCapacity(): void
    {
        $bulkhead = new Bulkhead(1);
        $bulkhead->tryAcquire();

        $this->expectException(BulkheadFullException::class);
        $bulkhead->tryAcquire();
    }

    public function testBulkheadWaitForCapacityReturnsFalseWhenFull(): void
    {
        $bulkhead = new Bulkhead(1);
        $bulkhead->tryAcquire();

        self::assertFalse($bulkhead->waitForCapacity());
    }

    public function testBulkheadZeroCapacityThrows(): void
    {
        $this->expectException(DomainException::class);
        new Bulkhead(0);
    }
}
