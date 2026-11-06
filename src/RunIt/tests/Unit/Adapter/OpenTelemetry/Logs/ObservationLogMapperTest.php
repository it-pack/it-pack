<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Logs;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\Logs\DefaultObservationLogMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SeverityMap;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Tests\Support\Fixture\OpenTelemetry\OtelTestKit;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;

/**
 * M9.6 — Observations to logs.
 */
final class ObservationLogMapperTest extends Unit
{
    private OtelTestKit $kit;

    protected function _before(): void
    {
        $this->kit = new OtelTestKit();
    }

    public function testMapsObservationToLogRecordWithCorrelation(): void
    {
        $mapper = new DefaultObservationLogMapper(
            $this->kit->loggerProvider,
            new SeverityMap(),
            mapToSpanEvents: false,
        );
        $observation = RunItArtifactFactory::observation(
            'obs-1',
            'exec-1',
            'info',
            'starting',
            'act-1',
            ['step' => 'init']
        );

        $mapper->map($observation);

        self::assertCount(1, $this->kit->logs());
        $record = $this->kit->log(0);
        self::assertSame('starting', $record->getBody());
        self::assertSame(Severity::INFO->value, $record->getSeverityNumber());
        self::assertSame('obs-1', $record->getAttributes()->get('runit.observation.id'));
        self::assertSame('exec-1', $record->getAttributes()->get('runit.execution.id'));
        self::assertSame('act-1', $record->getAttributes()->get('runit.activity.id'));
        self::assertSame('init', $record->getAttributes()->get('step'));
    }

    public function testMapsSeverityToOtelScale(): void
    {
        $mapper = new DefaultObservationLogMapper(
            $this->kit->loggerProvider,
            new SeverityMap(),
            mapToSpanEvents: false,
        );
        $mapper->map(RunItArtifactFactory::observation('obs-2', 'exec-1', 'critical', 'boom'));
        $mapper->map(RunItArtifactFactory::observation('obs-3', 'exec-1', 'debug', 'trace'));

        $this->kit->logs();
        self::assertSame(Severity::FATAL->value, $this->kit->log(0)->getSeverityNumber());
        self::assertSame(Severity::DEBUG->value, $this->kit->log(1)->getSeverityNumber());
    }

    public function testMapsThrowableUnderExceptionAttributes(): void
    {
        $mapper = new DefaultObservationLogMapper(
            $this->kit->loggerProvider,
            new SeverityMap(),
            mapToSpanEvents: false,
        );
        $observation = Observation::error(
            ObservationId::fromString('obs-4'),
            ExecutionId::fromString('exec-1'),
            'failed to write',
            throwableDescription: ThrowableDescription::create(
                'TypeError',
                'bad type',
                0,
                previous: null,
                trace: "stack\nhere",
            ),
        );

        $mapper->map($observation);

        $record = $this->kit->log(0);
        self::assertSame('TypeError', $record->getAttributes()->get('exception.type'));
        self::assertSame('bad type', $record->getAttributes()->get('exception.message'));
        self::assertSame("stack\nhere", $record->getAttributes()->get('exception.stacktrace'));
    }

    public function testMapsToSpanEventWhenEnabled(): void
    {
        $spanExporter = new InMemoryExporter();
        $span = new TracerProvider(new SimpleSpanProcessor($spanExporter))
            ->getTracer('test')
            ->spanBuilder('current')
            ->startSpan()
        ;
        $scope = $span->activate();

        try {
            $mapper = new DefaultObservationLogMapper(
                $this->kit->loggerProvider,
                new SeverityMap(),
                mapToSpanEvents: true,
            );
            $mapper->map(RunItArtifactFactory::observation('obs-5', 'exec-1', 'info', 'hello'));
        } finally {
            $scope->detach();
            $span->end();
        }

        $spans = $spanExporter->getSpans();
        /** @var SpanDataInterfacee $spanData */
        $spanData = $spans[0];
        $events = $spanData->getEvents();
        self::assertCount(1, $events);
        self::assertSame('runit.observation', $events[0]->getName());
    }

    public function testMissingLoggerProviderEmitsNothing(): void
    {
        $mapper = new DefaultObservationLogMapper(
            loggerProvider: null,
            severityMap: new SeverityMap(),
            mapToSpanEvents: false,
        );
        $mapper->map(RunItArtifactFactory::observation('obs-6', 'exec-1', 'info', 'hello'));

        self::assertCount(0, $this->kit->logs());
    }
}
