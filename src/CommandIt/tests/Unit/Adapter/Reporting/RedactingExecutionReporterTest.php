<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Reporting;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Common\RedactionPolicy;
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Adapter\Reporting\RedactingExecutionReporter;

/**
 * @covers \ItPack\CommandIt\Adapter\Reporting\RedactingExecutionReporter
 */
final class RedactingExecutionReporterTest extends Unit
{
    private CollectingExecutionReporter $delegate;

    private RedactingExecutionReporter $reporter;

    protected function setUp(): void
    {
        $this->delegate = new CollectingExecutionReporter();
        $this->reporter = new RedactingExecutionReporter($this->delegate, new RedactionPolicy());
    }

    public function testObservationContextIsRedacted(): void
    {
        $this->reporter->reportObservation('login', ['password' => 'hunter2', 'user' => 'bob']);

        $context = (array) $this->delegate->entries()[0]['context'];
        self::assertSame('***redacted***', $context['password']);
        self::assertSame('bob', $context['user']);
    }

    public function testOccurrenceContextIsRedacted(): void
    {
        $this->reporter->reportOccurrence('command.received.v1', ['token' => 'abc', 'command' => 'login']);

        $context = (array) $this->delegate->entries()[0]['context'];
        self::assertSame('***redacted***', $context['token']);
        self::assertSame('login', $context['command']);
    }

    public function testMeasurementPassesThroughUnchanged(): void
    {
        $this->reporter->reportMeasurement('duration_ms', 12, 'ms');

        $entry = $this->delegate->entries()[0];
        self::assertSame(12, $entry['value']);
    }

    public function testActivityIsForwardedUnchanged(): void
    {
        $id = $this->reporter->beginActivity('handler');
        $this->reporter->endActivity($id);

        self::assertNotEmpty($this->delegate->entries());
    }
}
