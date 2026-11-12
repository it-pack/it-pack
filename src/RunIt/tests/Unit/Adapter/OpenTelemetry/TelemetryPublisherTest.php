<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ActivitySpanMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanStoreInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\MeasurementInstrumentMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ObservationLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\OccurrenceLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\InMemoryExecutionSpanStore;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SnapshotPayloadPolicy;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;
use ItPack\RunIt\Application\Factory\PublisherStackFactory;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Metrics\Data\Sum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionProperty;
use RuntimeException;
use Tests\Support\Fixture\OpenTelemetry\OtelTestKit;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;
use Tests\Support\Fixture\SensitiveExceptionChain;
use Throwable;

/**
 * M9 — Telemetry publisher end-to-end dispatch.
 */
#[CoversClass(TelemetryPublisher::class)]
#[UsesClass(DefaultArtifactRedactor::class)]
final class TelemetryPublisherTest extends Unit
{
    private OtelTestKit $kit;

    /** @var array<string, mixed>|null */
    private ?array $globalState = null;

    protected function _before(): void
    {
        $this->kit = new OtelTestKit();
    }

    protected function _after(): void
    {
        if ($this->globalState !== null) {
            foreach ($this->globalState as $name => $value) {
                new ReflectionProperty(Globals::class, $name)->setValue(null, $value);
            }
        }
    }

    private function publisher(): TelemetryPublisher
    {
        $config = new BridgeConfigBuilder()
            ->withTracerProvider($this->kit->tracerProvider)
            ->withMeterProvider($this->kit->meterProvider)
            ->withLoggerProvider($this->kit->loggerProvider)
            ->withOccurrenceLogging(enabled: true)
            ->withOccurrenceSpanEvents(enabled: false)
            ->build()
        ;

        return TelemetryPublisher::fromConfig($config);
    }

    public function testCapabilityReflectsConfiguredProviders(): void
    {
        $publisher = $this->publisher();

        $capability = $publisher->capability();
        self::assertTrue($capability->trace);
        self::assertTrue($capability->metrics);
        self::assertTrue($capability->logs);
        self::assertTrue($capability->any());
    }

    public function testPublishesExecutionAndActivityAsSpans(): void
    {
        $publisher = $this->publisher();
        $publisher->publish(RunItArtifactFactory::execution('exec-1', 'import', 'queue.consumer.process', 'succeeded'));
        $publisher->publish(RunItArtifactFactory::activity('act-1', 'exec-1', 'parse', 'internal', 'succeeded'));

        $spans = $this->kit->spans();
        self::assertCount(2, $spans);
        self::assertSame('import', $spans[0]->getName());
        self::assertSame('parse', $spans[1]->getName());
        self::assertSame($spans[0]->getContext()->getSpanId(), $spans[1]->getParentSpanId());
    }

    /** Characterizes retention and replacement across repeated publications. */
    public function testInjectedStoreRetainsContextsAndDuplicateIdsReplaceThem(): void
    {
        $store = new InMemoryExecutionSpanStore();
        $config = new BridgeConfigBuilder()->withTracerProvider($this->kit->tracerProvider)->build();
        $publisher = TelemetryPublisher::fromConfig($config, $store);
        for ($i = 0; $i < 100; ++$i) {
            $publisher->publish(RunItArtifactFactory::execution('exec-' . $i, 'exec', 'internal', 'succeeded'));
            $publisher->publish(RunItArtifactFactory::activity(
                'act-' . $i,
                'exec-' . $i,
                'act',
                'internal',
                'succeeded',
            ));
        }

        $spans = $this->kit->spans();
        self::assertSame($spans[0]->getContext()->getSpanId(), $store->executionContext('exec-0')?->getSpanId());
        self::assertSame($spans[1]->getContext()->getSpanId(), $store->activityContext('act-0')?->getSpanId());

        $publisher->publish(RunItArtifactFactory::execution('exec-0', 'replacement', 'internal', 'succeeded'));
        $publisher->publish(RunItArtifactFactory::activity('act-0', 'exec-0', 'replacement', 'internal', 'succeeded'));
        $publisher->publish(RunItArtifactFactory::activity('late', 'exec-0', 'late', 'internal', 'succeeded', 'act-0'));

        $spans = $this->kit->spans();
        self::assertSame($spans[200]->getContext()->getSpanId(), $store->executionContext('exec-0')->getSpanId());
        self::assertSame($spans[201]->getContext()->getSpanId(), $store->activityContext('act-0')->getSpanId());
        self::assertSame($spans[200]->getContext()->getSpanId(), $spans[201]->getParentSpanId());
        self::assertSame($spans[201]->getContext()->getSpanId(), $spans[202]->getParentSpanId());
    }

    /** Characterizes lookups when parents arrive after children. */
    public function testMissingParentsFallBackAndLaterParentsAreNotRetroactive(): void
    {
        $publisher = $this->publisher();
        $publisher->publish(RunItArtifactFactory::execution('child', 'child', 'internal', 'succeeded', 'parent'));
        $publisher->publish(RunItArtifactFactory::execution('parent', 'parent', 'internal', 'succeeded'));
        $publisher->publish(RunItArtifactFactory::activity(
            'early',
            'parent',
            'early',
            'internal',
            'succeeded',
            'outer',
        ));
        $publisher->publish(RunItArtifactFactory::activity('outer', 'parent', 'outer', 'internal', 'succeeded'));
        $publisher->publish(RunItArtifactFactory::activity('late', 'parent', 'late', 'internal', 'succeeded', 'outer'));

        $spans = $this->kit->spans();
        self::assertNotSame($spans[1]->getContext()->getSpanId(), $spans[0]->getParentSpanId());
        self::assertSame($spans[1]->getContext()->getSpanId(), $spans[2]->getParentSpanId());
        self::assertSame($spans[3]->getContext()->getSpanId(), $spans[4]->getParentSpanId());
    }

    /** Characterizes delegation to an externally supplied store. */
    public function testPublisherUsesInjectedStoreForBothMappers(): void
    {
        $store = $this->createMock(ExecutionSpanStoreInterface::class);
        $store->expects($this->once())->method('recordExecution')->with('exec', self::anything());
        $store->expects($this->once())->method('activityContext')->with('missing')->willReturn(null);
        $store->expects($this->once())->method('executionContext')->with('exec')->willReturn(null);
        $store->expects($this->once())->method('recordActivity')->with('act', self::anything());
        $config = new BridgeConfigBuilder()->withTracerProvider($this->kit->tracerProvider)->build();
        $publisher = TelemetryPublisher::fromConfig($config, $store);
        $publisher->publish(RunItArtifactFactory::execution('exec', 'exec', 'internal', 'succeeded'));
        $publisher->publish(RunItArtifactFactory::activity('act', 'exec', 'act', 'internal', 'succeeded', 'missing'));
    }

    public function testFailedExecutionRedactionMasksOuterThrowableInSpanEvent(): void
    {
        $this->assertFailedExecutionExport(new ArtifactRedactionConfig());
    }

    public function testFailedExecutionExportWithThrowableRedactionDisabled(): void
    {
        $this->assertFailedExecutionExport(new ArtifactRedactionConfig(redactThrowableMessages: false));
    }

    private function assertFailedExecutionExport(ArtifactRedactionConfig $config): void
    {
        $execution = Execution::start(
            ExecutionId::fromString('exec-sensitive'),
            ExecutionDefinition::create(ExecutionName::fromString('import'), ExecutionKind::fromString('batch')),
            Instant::of(1000),
        )->complete(
            ExecutionFailure::of(ExecutionStatus::Failed, SensitiveExceptionChain::classifiedChain()),
            Instant::of(1001),
        );
        $before = $execution->normalize();
        $publisher = new PublisherStackFactory()->compose(
            $this->publisher(),
            new DefaultArtifactRedactor($config),
            null,
        );
        $publisher->publish($execution);

        $spans = $this->kit->spans();
        self::assertCount(1, $spans);
        $span = $spans[0];
        self::assertSame('import', $span->getName());
        self::assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        self::assertSame('exec-sensitive', $span->getAttributes()->get('runit.execution.id'));
        self::assertSame('failed', $span->getAttributes()->get('runit.execution.status'));
        $events = $span->getEvents();
        self::assertCount(1, $events);
        self::assertSame('exception', $events[0]->getName());
        $attributes = $events[0]->getAttributes();
        self::assertSame('RuntimeException', $attributes->get('exception.type'));
        $message = $attributes->get('exception.message');
        $trace = $attributes->get('exception.stacktrace');
        self::assertIsString($message);
        $secrets = SensitiveExceptionChain::secrets();
        if ($config->redactThrowableMessages) {
            // Redacted throwable: no classified secret reaches the exporter.
            foreach ($secrets as $secret) {
                self::assertFalse(str_contains($message, $secret), 'Secret must not reach the exporter.');
                if ($trace !== null) {
                    self::assertIsString($trace);
                    self::assertFalse(str_contains($trace, $secret), 'Secret trace must not reach the exporter.');
                }
            }

            self::assertSame('[redacted]', $message);
            // Traces are omitted by default, not masked: the attribute is absent.
            self::assertArrayNotHasKey('exception.stacktrace', $attributes->toArray());
        } else {
            // Redaction disabled: secrets reach the exporter (known behavior, not a leak).
            $outerSecret = $secrets[1]; // db password in outer throwable message
            self::assertIsString($trace);
            self::assertStringContainsString($outerSecret, $message);
            self::assertStringContainsString($outerSecret, $trace);
        }

        self::assertCount(0, $this->kit->logs());
        self::assertSame($before, $execution->normalize(), 'The original execution remains unchanged.');
    }

    public function testPublishesMeasurementAsMetric(): void
    {
        $publisher = $this->publisher();
        $publisher->publish(RunItArtifactFactory::counter('exec-1', 'items', 7));

        $metrics = $this->kit->collectMetrics();
        $found = false;
        foreach ($metrics as $metric) {
            if ($metric->name === 'items') {
                $found = true;
            }
        }

        self::assertTrue($found);
    }

    /** Characterizes dispatch of changed activity and measurement projections. */
    public function testRedactedActivityAndMeasurementComparedWithTypedControls(): void
    {
        $activity = Activity::start(
            ActivityId::fromString('act-red'),
            ExecutionId::fromString('exec-1'),
            ActivityDefinition::create(ActivityName::fromString('parse'), ActivityKind::fromString('internal')),
            Instant::of(1100),
            attributes: AttributeCollection::fromArray(['password' => 'secret']),
        )->complete(ActivityStatus::Succeeded, null, Instant::of(1200));
        $measurement = RunItArtifactFactory::counter('exec-1', 'redacted-items', 7, ['password' => 'secret']);
        $activityBefore = $activity->normalize();
        $measurementBefore = $measurement->normalize();
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(sensitiveAttributeKeys: ['password']));
        $redactedActivity = $redactor->redact($activity);
        $redactedMeasurement = $redactor->redact($measurement);

        foreach ([$redactedActivity, $redactedMeasurement] as $redacted) {
            self::assertInstanceOf(RedactedArtifactInterface::class, $redacted);
            $normalized = $redacted->normalize();
            self::assertIsArray($normalized['attributes']);
            self::assertSame('[redacted]', $normalized['attributes']['password']);
        }

        $publisher = $this->publisher();
        $publisher->publish($redactedActivity);
        $publisher->publish($redactedMeasurement);

        // Redacted activity still produces a span (with masked attributes);
        // the publisher ignores unrouted redacted artifacts such as measurements.
        self::assertCount(1, $this->kit->spans());
        self::assertCount(0, $this->kit->collectMetrics());
        self::assertCount(0, $this->kit->logs());

        $span = $this->kit->spans()[0];
        self::assertSame('parse', $span->getName());
        $attributes = $span->getAttributes()->toArray();
        self::assertSame('[redacted]', $attributes['password']);
        self::assertArrayNotHasKey('secret', $attributes);

        $control = RunItArtifactFactory::activity('act-control', 'exec-1', 'control', 'internal', 'succeeded');
        $publisher->publish($control);
        $publisher->publish(RunItArtifactFactory::counter('exec-1', 'control-items', 7));
        self::assertCount(2, $this->kit->spans());
        self::assertSame('control', $this->kit->spans()[1]->getName());
        $metrics = $this->kit->collectMetrics();
        self::assertCount(1, $metrics);
        self::assertSame('control-items', $metrics[0]->name);
        self::assertCount(0, $this->kit->logs());
        self::assertSame($activityBefore, $activity->normalize());
        self::assertSame($measurementBefore, $measurement->normalize());

        $disabled = TelemetryPublisher::fromConfig(BridgeConfig::default());
        self::assertFalse($disabled->capability()->any());
        $disabled->publish($redactedActivity);
        $disabled->publish($redactedMeasurement);
        $disabled->publish($activity);
        $disabled->publish($measurement);
        self::assertCount(2, $this->kit->spans());
        self::assertCount(1, $this->kit->collectMetrics());
        self::assertCount(0, $this->kit->logs());
    }

    public function testPublishesObservationAndOccurrenceAndSnapshotAsLogs(): void
    {
        $publisher = $this->publisher();
        $publisher->publish(RunItArtifactFactory::observation('obs-1', 'exec-1', 'info', 'hi'));
        $publisher->publish(RunItArtifactFactory::occurrence('occ-1', 'exec-1', 'execution.started.v1'));
        $publisher->publish(RunItArtifactFactory::snapshot('snap-1', 'exec-1', ['ok' => true]));

        self::assertCount(3, $this->kit->logs());
    }

    /** Disabling observation logs does not disable span events or occurrence logs. */
    public function testObservationLoggingCanBeDisabledIndependently(): void
    {
        $config = new BridgeConfigBuilder()
            ->withLoggerProvider($this->kit->loggerProvider)
            ->withObservationLogging(false)
            ->withObservationSpanEvents(true)
            ->withOccurrenceSpanEvents(false)
            ->build()
        ;
        $publisher = TelemetryPublisher::fromConfig($config);
        $observation = RunItArtifactFactory::observation(
            'obs',
            'exec',
            'warning',
            'secret',
            attributes: ['password' => 'secret'],
        );
        $redacted = new DefaultArtifactRedactor(new ArtifactRedactionConfig(sensitiveAttributeKeys: ['password']))
            ->redact($observation)
        ;
        $span = $this->kit->tracerProvider->getTracer('test')->spanBuilder('current')->startSpan();
        $scope = $span->activate();
        try {
            $publisher->publish($observation);
            $publisher->publish($redacted);
            $publisher->publish(RunItArtifactFactory::occurrence('occ', 'exec', 'execution.started.v1'));
        } finally {
            $scope->detach();
            $span->end();
        }

        self::assertCount(1, $this->kit->logs());
        $events = $this->kit->spans()[0]->getEvents();
        self::assertCount(2, $events);
        // The typed observation passes its attributes through; the redacted
        // observation suppresses its free-form attribute map entirely.
        self::assertSame('secret', $events[0]->getAttributes()->get('password'));
        self::assertArrayNotHasKey('password', $events[1]->getAttributes()->toArray());
    }

    /** Unsupported redacted observations are reported via health and dropped, never thrown. */
    public function testUnsupportedRedactedObservationReportsViaHealthAndDrops(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(
            static function (Throwable $error) use (&$reports): void {
                $reports[] = $error;
            },
        );
        $mapper = $this->createMock(ObservationLogMapperInterface::class);
        $mapper->expects($this->once())->method('map');
        $publisher = new TelemetryPublisher(
            BridgeConfig::default(),
            $this->createMock(ExecutionSpanMapperInterface::class),
            $this->createMock(ActivitySpanMapperInterface::class),
            $this->createMock(MeasurementInstrumentMapperInterface::class),
            $mapper,
            $this->createMock(OccurrenceLogMapperInterface::class),
            $health,
        );
        $observation = RunItArtifactFactory::observation('obs', 'exec', 'info', 'secret');
        $redacted = new DefaultArtifactRedactor(new ArtifactRedactionConfig())->redact($observation);
        self::assertInstanceOf(RedactedArtifactInterface::class, $redacted);

        $publisher->publish($redacted);

        self::assertCount(1, $reports);
        self::assertInstanceOf(RuntimeException::class, $reports[0]);
        self::assertSame('Observation mapper does not support redacted observations.', $reports[0]->getMessage());
        self::assertCount(0, $this->kit->logs(), 'No redacted observation is exported.');

        $publisher->publish($observation);
    }

    /** Redacted observations are delivered as sanitized OpenTelemetry log records. */
    public function testRedactedObservationIsDeliveredAsSanitizedLog(): void
    {
        $observation = RunItArtifactFactory::observation(
            'obs-red',
            'exec-1',
            'info',
            'observed',
            attributes: ['password' => 'secret'],
        );
        $redacted = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['password'],
        ))->redact($observation);

        self::assertInstanceOf(RedactedArtifactInterface::class, $redacted);
        $normalized = $redacted->normalize();
        self::assertIsArray($normalized['attributes']);
        self::assertSame('[redacted]', $normalized['attributes']['password']);
        self::assertSame('[redacted]', $normalized['message']);
        self::assertArrayNotHasKey('source', $normalized);
        self::assertArrayNotHasKey('classification', $normalized);

        $config = new BridgeConfigBuilder()
            ->withLoggerProvider($this->kit->loggerProvider)
            ->withObservationSpanEvents(enabled: true)
            ->build()
        ;
        $publisher = TelemetryPublisher::fromConfig($config);
        $span = $this->kit->tracerProvider->getTracer('test')->spanBuilder('current')->startSpan();
        $scope = $span->activate();
        try {
            $publisher->publish($redacted);
            self::assertCount(1, $this->kit->logs());
            self::assertSame('[redacted]', $this->kit->log(0)->getBody());
            $publisher->publish(RunItArtifactFactory::observation('obs-control', 'exec-1', 'info', 'control'));
        } finally {
            $scope->detach();
            $span->end();
        }

        self::assertCount(2, $this->kit->logs());
        self::assertSame('control', $this->kit->log(1)->getBody());
        $events = $this->kit->spans()[0]->getEvents();
        self::assertCount(2, $events);
        self::assertSame('runit.observation', $events[0]->getName());
        self::assertSame('runit.observation', $events[1]->getName());
    }

    public function testRedactedOccurrenceProducesVisibleSuppressedLog(): void
    {
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveDataKeys: ['password'],
            redactOccurrenceData: true,
        ));

        $occurrence = RunItArtifactFactory::occurrence(
            'occ-red',
            'exec-1',
            'execution.started.v1',
            ['password' => 'hunter2']
        );
        $redacted = $redactor->redact($occurrence);

        self::assertInstanceOf(
            RedactedArtifactInterface::class,
            $redacted,
            'A redacted artifact is marked as suppressed.'
        );

        $publisher = $this->publisher();
        $publisher->publish($redacted);

        self::assertGreaterThanOrEqual(
            1,
            $this->kit->logs()->count(),
            'A redacted occurrence is still delivered, not silently dropped.'
        );

        $redactedLogged = false;
        foreach ($this->kit->logs() as $record) {
            foreach ($record->getAttributes() as $name => $value) {
                if ($name === 'runit.redacted' && $value === true) {
                    $redactedLogged = true;
                }
            }
        }

        self::assertTrue($redactedLogged, 'The suppressed delivery is marked runit.redacted.');
    }

    public function testRedactedSnapshotIsDeliveredThroughTheSnapshotLogPath(): void
    {
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveDataKeys: ['secret'],
            redactSnapshotPayloads: true,
        ));

        $snapshot = RunItArtifactFactory::snapshot('snap-red', 'exec-1', ['secret' => 'value']);
        $redacted = $redactor->redact($snapshot);

        self::assertInstanceOf(RedactedArtifactInterface::class, $redacted);

        $publisher = $this->publisher();
        $publisher->publish($redacted);

        $delivered = false;
        foreach ($this->kit->logs() as $record) {
            $attributes = $record->getAttributes()->toArray();

            if (($attributes['runit.snapshot.classification'] ?? null) !== null) {
                $delivered = true;
                self::assertTrue($attributes['runit.redacted']);
            }
        }

        self::assertTrue(
            $delivered,
            'A redacted snapshot routes through the snapshot log mapper, not the occurrence path.'
        );
    }

    /** Characterizes extension omission and subject exposure on redacted occurrences. */
    public function testRedactedOccurrenceOmitsExtensionAttributes(): void
    {
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-characterized'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('payment.captured.v1'),
            'it-pack/test',
            Instant::of(1600),
            ['password' => 'secret'],
            subject: 'synthetic-subject-secret',
            extensionAttributes: AttributeCollection::fromArray(
                ['allowed' => 'yes', 'excluded' => 'omitted-by-redaction']
            ),
        );
        $redacted = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveDataKeys: ['password'],
            redactOccurrenceData: true,
        ))->redact($occurrence);
        self::assertInstanceOf(RedactedArtifactInterface::class, $redacted);
        $normalized = $redacted->normalize();
        self::assertSame(1600, $normalized['time']);
        self::assertIsArray($normalized['data']);
        self::assertNotSame('secret', $normalized['data']['password']);
        $originalData = $occurrence->normalize()['data'];
        self::assertIsArray($originalData);
        self::assertSame('secret', $originalData['password']);
        self::assertSame('synthetic-subject-secret', $normalized['subject']);
        self::assertIsArray($normalized['extension_attributes']);
        self::assertSame('omitted-by-redaction', $normalized['extension_attributes']['excluded']);

        $config = new BridgeConfigBuilder()
            ->withLoggerProvider($this->kit->loggerProvider)
            ->withOccurrenceLogging(enabled: true)
            ->withOccurrenceSpanEvents(enabled: true)
            ->withOccurrenceLogAttributeAllowList(['allowed'])
            ->build()
        ;
        $span = $this->kit->tracerProvider->getTracer('test')->spanBuilder('current')->startSpan();
        $scope = $span->activate();
        try {
            TelemetryPublisher::fromConfig($config)->publish($redacted);
        } finally {
            $scope->detach();
            $span->end();
        }

        self::assertCount(1, $this->kit->logs());
        $record = $this->kit->log(0);
        self::assertSame('runit.occurrence', $record->getEventName());
        self::assertSame('payment.captured.v1', $record->getBody());
        self::assertSame(1_600_000_000_000, $record->getTimestamp());
        $attributes = $record->getAttributes()->toArray();
        self::assertSame('occ-characterized', $attributes['runit.occurrence.id']);
        self::assertTrue($attributes['runit.redacted']);
        self::assertArrayNotHasKey('runit.occurrence.subject', $attributes);
        self::assertArrayNotHasKey('allowed', $attributes);
        self::assertArrayNotHasKey('excluded', $attributes);
        self::assertArrayNotHasKey('runit.occurrence.content_type', $attributes);
        self::assertArrayNotHasKey('password', $attributes);
        $events = $this->kit->spans()[0]->getEvents();
        self::assertCount(1, $events);
        self::assertSame('runit.occurrence', $events[0]->getName());
        self::assertArrayNotHasKey('runit.occurrence.subject', $events[0]->getAttributes()->toArray());
        self::assertArrayNotHasKey('excluded', $events[0]->getAttributes()->toArray());
    }

    /** Verifies the None policy and the redacted snapshot timestamp. */
    public function testActualRedactedSnapshotOmitsHashWithNonePolicyAndUsesCreatedAt(): void
    {
        $snapshot = RunItArtifactFactory::snapshot('snap-characterized', 'exec-1', ['secret' => 'value'], 'act-1');
        $redacted = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveDataKeys: ['secret'],
            redactSnapshotPayloads: true,
        ))->redact($snapshot);
        self::assertInstanceOf(RedactedArtifactInterface::class, $redacted);
        $normalized = $redacted->normalize();
        self::assertSame(1700, $normalized['created_at']);
        self::assertArrayNotHasKey('time', $normalized);
        self::assertSame($snapshot->contentHash(), $normalized['content_hash']);
        self::assertIsArray($normalized['payload']);
        self::assertNotSame('value', $normalized['payload']['secret']);

        $config = new BridgeConfigBuilder()
            ->withLoggerProvider($this->kit->loggerProvider)
            ->withSnapshotPayloadPolicy(SnapshotPayloadPolicy::None)
            ->withOccurrenceLogging(enabled: true)
            ->withOccurrenceSpanEvents(enabled: true)
            ->build()
        ;
        $span = $this->kit->tracerProvider->getTracer('test')->spanBuilder('current')->startSpan();
        $scope = $span->activate();
        try {
            TelemetryPublisher::fromConfig($config)->publish($redacted);
        } finally {
            $scope->detach();
            $span->end();
        }

        self::assertCount(1, $this->kit->logs());
        $record = $this->kit->log(0);
        self::assertSame('runit.snapshot', $record->getEventName());
        self::assertSame('execution.snapshot', $record->getBody());
        self::assertSame(1_700_000_000_000, $record->getTimestamp());
        $attributes = $record->getAttributes()->toArray();
        self::assertSame('snap-characterized', $attributes['runit.snapshot.id']);
        self::assertSame('act-1', $attributes['runit.activity.id']);
        self::assertTrue($attributes['runit.redacted']);
        self::assertArrayNotHasKey('runit.snapshot.hash', $attributes);
        self::assertArrayNotHasKey('runit.snapshot.payload', $attributes);
        self::assertArrayNotHasKey('runit.snapshot.schema_version', $attributes);
        self::assertArrayNotHasKey('runit.snapshot.content_type', $attributes);
        $events = $this->kit->spans()[0]->getEvents();
        self::assertCount(1, $events);
        self::assertSame('runit.snapshot', $events[0]->getName());
        self::assertArrayNotHasKey('runit.snapshot.hash', $events[0]->getAttributes()->toArray());
    }

    public function testDefaultConfigExportsNothingAndHasNoCapability(): void
    {
        $this->globalState = [];
        foreach (['globals', 'initializers'] as $name) {
            $this->globalState[$name] = new ReflectionProperty(Globals::class, $name)->getValue();
        }

        Globals::reset();
        Globals::registerInitializer(fn (Configurator $config): Configurator => $config
            ->withTracerProvider($this->kit->tracerProvider)
            ->withMeterProvider($this->kit->meterProvider)
            ->withLoggerProvider($this->kit->loggerProvider));
        $control = TelemetryPublisher::fromConfig(new BridgeConfigBuilder()
            ->withTracerProvider(Globals::tracerProvider())
            ->withMeterProvider(Globals::meterProvider())
            ->withLoggerProvider(Globals::loggerProvider())
            ->build());
        $control->publish(RunItArtifactFactory::activity('act-control', 'exec-2', 'control', 'internal', 'succeeded'));
        $control->publish(RunItArtifactFactory::counter('exec-2', 'items', 7));
        $control->publish(RunItArtifactFactory::observation('obs-control', 'exec-2', 'info', 'control'));
        self::assertCount(1, $this->kit->spans());
        self::assertSame('control', $this->kit->spans()[0]->getName());
        self::assertCount(1, $this->kit->logs());
        $metrics = $this->kit->collectMetrics();
        self::assertCount(1, $metrics);
        self::assertSame('items', $metrics[0]->name);
        self::assertInstanceOf(Sum::class, $metrics[0]->data);
        $points = iterator_to_array($metrics[0]->data->dataPoints);
        self::assertCount(1, $points);
        self::assertSame(7, array_values($points)[0]->value);

        $publisher = TelemetryPublisher::fromConfig(BridgeConfig::default());

        $publisher->publish(RunItArtifactFactory::execution('exec-2', 'import', 'internal', 'succeeded'));
        $publisher->publish(RunItArtifactFactory::activity('act-2', 'exec-2', 'parse', 'internal', 'succeeded'));
        $publisher->publish(RunItArtifactFactory::counter('exec-2', 'items', 1));
        $publisher->publish(RunItArtifactFactory::observation('obs-2', 'exec-2', 'info', 'hi'));
        $publisher->publish(RunItArtifactFactory::occurrence('occ-2', 'exec-2', 'execution.started.v1'));
        $publisher->publish(RunItArtifactFactory::snapshot('snap-2', 'exec-2', ['ok' => true]));

        self::assertCount(1, $this->kit->spans());
        self::assertCount(1, $this->kit->logs());
        $metrics = $this->kit->collectMetrics();
        self::assertCount(1, $metrics);
        self::assertSame('items', $metrics[0]->name);
        self::assertInstanceOf(Sum::class, $metrics[0]->data);
        $points = iterator_to_array($metrics[0]->data->dataPoints);
        // The test reader uses delta temporality, so the first collection consumed the control value.
        self::assertCount(0, $points);
        self::assertFalse($publisher->capability()->any());
    }
}
