<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Logs;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\Logs\DefaultObservationLogMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SeverityMap;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
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

    /** Redacted projections use the same severity bands as typed observations. */
    public function testRedactedSeverityMatchesTypedSeverity(): void
    {
        $mapper = new DefaultObservationLogMapper($this->kit->loggerProvider, new SeverityMap());
        foreach (ObservationSeverity::cases() as $index => $severity) {
            $mapper->mapRedactedObservation(['level' => $severity->level(), 'message' => 'safe']);
            self::assertSame(new SeverityMap()->toNumber($severity), $this->kit->log($index)->getSeverityNumber());
            self::assertSame($severity->label(), $this->kit->log($index)->getSeverityText());
        }
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
        /** @var SpanDataInterface $spanData */
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

    public function testRedactedObservationExportsOnlyClassifiedFields(): void
    {
        $mapper = new DefaultObservationLogMapper(
            $this->kit->loggerProvider,
            new SeverityMap(),
            mapToSpanEvents: false,
        );
        $mapper->mapRedactedObservation([
            'id' => 'obs-red',
            'execution_id' => 'exec-9',
            'activity_id' => 'act-9',
            'diagnostic_code' => 'NETWORK',
            'level' => 400,
            'message' => 'safe message',
            'timestamp' => 1_800,
            'attributes' => ['password' => 'secret', 'step' => 'init'],
            'throwable' => [
                'class' => 'RuntimeException',
                'code' => 7,
                'message' => '[redacted]',
                'trace' => null,
                'previous' => null,
            ],
            'unclassified_field' => 'must-not-export',
        ]);

        $record = $this->kit->log(0);
        self::assertSame('safe message', $record->getBody());
        self::assertSame(Severity::ERROR->value, $record->getSeverityNumber());
        self::assertSame('error', $record->getSeverityText());
        self::assertSame(1_800_000_000_000, $record->getTimestamp());
        self::assertSame([
            'runit.observation.id' => 'obs-red',
            'runit.execution.id' => 'exec-9',
            'runit.activity.id' => 'act-9',
            'runit.diagnostic.code' => 'NETWORK',
            'exception.type' => 'RuntimeException',
            'exception.message' => '[redacted]',
        ], $record->getAttributes()->toArray());
        self::assertArrayNotHasKey('password', $record->getAttributes()->toArray());
        self::assertArrayNotHasKey('step', $record->getAttributes()->toArray());
        self::assertArrayNotHasKey('unclassified_field', $record->getAttributes()->toArray());
        self::assertArrayNotHasKey('exception.stacktrace', $record->getAttributes()->toArray());
    }

    public function testRedactedObservationOmitsUnsafeIdentifiers(): void
    {
        $mapper = new DefaultObservationLogMapper(
            $this->kit->loggerProvider,
            new SeverityMap(),
            mapToSpanEvents: false,
        );
        $mapper->mapRedactedObservation([
            'id' => 12345,
            'execution_id' => null,
            'diagnostic_code' => null,
            'level' => 400,
            'message' => 'safe',
        ]);

        $record = $this->kit->log(0);
        self::assertSame('safe', $record->getBody());
        $attributes = $record->getAttributes()->toArray();
        self::assertArrayNotHasKey('runit.observation.id', $attributes, 'A non-string observation id is suppressed.');
        self::assertArrayNotHasKey('runit.execution.id', $attributes, 'A missing execution id is suppressed.');
        self::assertArrayNotHasKey('runit.diagnostic.code', $attributes);
    }

    public function testRedactedObservationFromRedactorExportsClassifiedFieldsOnly(): void
    {
        $observation = RunItArtifactFactory::observation(
            'obs-red',
            'exec-1',
            'error',
            'token=abc123 failed',
            'act-1',
            ['password' => 'secret', 'step' => 'init'],
        );
        $redacted = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['password'],
        ))->redact($observation);
        self::assertInstanceOf(RedactedArtifactInterface::class, $redacted);

        $mapper = new DefaultObservationLogMapper(
            $this->kit->loggerProvider,
            new SeverityMap(),
            mapToSpanEvents: false,
        );
        $mapper->mapRedactedObservation($redacted->normalize());

        $record = $this->kit->log(0);
        self::assertSame('[redacted]', $record->getBody(), 'The observation message is redacted before mapping.');
        $attributes = $record->getAttributes()->toArray();
        self::assertSame('obs-red', $attributes['runit.observation.id']);
        self::assertSame('exec-1', $attributes['runit.execution.id']);
        self::assertSame('act-1', $attributes['runit.activity.id']);
        self::assertArrayNotHasKey('password', $attributes, 'Free-form attributes are suppressed.');
        self::assertArrayNotHasKey('step', $attributes, 'Free-form attributes are suppressed.');
    }
}
