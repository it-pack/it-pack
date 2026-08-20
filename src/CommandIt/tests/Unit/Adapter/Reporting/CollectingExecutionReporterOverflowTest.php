<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Reporting;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Reporting\BufferOverflowException;
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Adapter\Reporting\OverflowPolicy;

/**
 * @covers \ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter
 * @covers \ItPack\CommandIt\Adapter\Reporting\OverflowPolicy
 * @covers \ItPack\CommandIt\Adapter\Reporting\BufferOverflowException
 */
final class CollectingExecutionReporterOverflowTest extends Unit
{
    public function testDropOldestKeepsMostRecent(): void
    {
        $reporter = new CollectingExecutionReporter(limit: 2, overflow: OverflowPolicy::DropOldest);

        $reporter->reportObservation('a');
        $reporter->reportObservation('b');
        $reporter->reportObservation('c');

        self::assertCount(2, $reporter->entries());
        self::assertSame('b', $reporter->entries()[0]['message']);
        self::assertSame('c', $reporter->entries()[1]['message']);
    }

    public function testDropNewestKeepsOriginal(): void
    {
        $reporter = new CollectingExecutionReporter(limit: 2, overflow: OverflowPolicy::DropNewest);

        $reporter->reportObservation('a');
        $reporter->reportObservation('b');
        $reporter->reportObservation('c');

        self::assertCount(2, $reporter->entries());
        self::assertSame('a', $reporter->entries()[0]['message']);
        self::assertSame('b', $reporter->entries()[1]['message']);
    }

    public function testThrowPolicyRaisesBufferOverflowException(): void
    {
        $reporter = new CollectingExecutionReporter(limit: 1, overflow: OverflowPolicy::Throw);

        $reporter->reportObservation('a');

        $this->expectException(BufferOverflowException::class);
        $reporter->reportObservation('b');
    }

    public function testZeroLimitDiscardsEverything(): void
    {
        $reporter = new CollectingExecutionReporter(limit: 0);

        $reporter->reportObservation('a');

        self::assertCount(0, $reporter->entries());
    }
}
