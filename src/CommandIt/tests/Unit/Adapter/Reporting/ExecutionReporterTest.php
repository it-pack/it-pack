<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Reporting;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Adapter\Reporting\NullExecutionReporter;

/**
 * @covers \ItPack\CommandIt\Adapter\Reporting\NullExecutionReporter
 * @covers \ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter
 */
final class ExecutionReporterTest extends Unit
{
    public function testNullReporterIsSilent(): void
    {
        $reporter = new NullExecutionReporter();
        $reporter->reportObservation('x');
        $reporter->reportOccurrence('t');
        $reporter->reportMeasurement('m', 1, 'ms');
        $reporter->endActivity($reporter->beginActivity('a'));

        $this->expectNotToPerformAssertions();
    }

    public function testCollectingReporterRecordsEntries(): void
    {
        $reporter = new CollectingExecutionReporter();
        $reporter->reportObservation('hello', ['a' => 1]);
        $reporter->reportOccurrence('command.received.v1');
        $reporter->reportMeasurement('duration', 5, 'ms');
        $id = $reporter->beginActivity('work');
        $reporter->endActivity($id);

        $entries = $reporter->entries();

        self::assertCount(5, $entries);
        self::assertSame('observation', $entries[0]['type']);
        self::assertSame('command.received.v1', $entries[1]['name']);
        self::assertSame('duration', $entries[2]['name']);
        self::assertSame('activity_start', $entries[3]['type']);
        self::assertSame('activity_end', $entries[4]['type']);
    }

    public function testCollectingReporterBoundsBuffer(): void
    {
        $reporter = new CollectingExecutionReporter(2);

        $reporter->reportObservation('a');
        $reporter->reportObservation('b');
        $reporter->reportObservation('c');

        $entries = $reporter->entries();

        self::assertCount(2, $entries);
        self::assertSame('b', $entries[0]['message']);
        self::assertSame('c', $entries[1]['message']);
    }
}
