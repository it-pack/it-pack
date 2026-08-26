<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeCapability;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use Tests\Support\Fixture\OpenTelemetry\OtelTestKit;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;

/**
 * M9 — Telemetry publisher end-to-end dispatch.
 */
final class TelemetryPublisherTest extends Unit
{
    private OtelTestKit $kit;

    protected function _before(): void
    {
        $this->kit = new OtelTestKit();
    }

    private function publisher(): TelemetryPublisher
    {
        $config = new BridgeConfigBuilder()
            ->withTracerProvider($this->kit->tracerProvider)
            ->withMeterProvider($this->kit->meterProvider)
            ->withLoggerProvider($this->kit->loggerProvider)
            ->withOccurrenceLogging(true)
            ->withOccurrenceSpanEvents(false)
            ->build()
        ;

        return TelemetryPublisher::fromConfig($config);
    }

    public function testCapabilityReflectsConfiguredProviders(): void
    {
        $publisher = $this->publisher();

        $capability = $publisher->capability();
        self::assertInstanceOf(BridgeCapability::class, $capability);
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

    public function testPublishesObservationAndOccurrenceAndSnapshotAsLogs(): void
    {
        $publisher = $this->publisher();
        $publisher->publish(RunItArtifactFactory::observation('obs-1', 'exec-1', 'info', 'hi'));
        $publisher->publish(RunItArtifactFactory::occurrence('occ-1', 'exec-1', 'execution.started.v1'));
        $publisher->publish(RunItArtifactFactory::snapshot('snap-1', 'exec-1', ['ok' => true]));

        self::assertCount(3, $this->kit->logs());
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

    public function testDefaultConfigExportsNothingAndHasNoCapability(): void
    {
        $publisher = TelemetryPublisher::fromConfig(BridgeConfig::default());

        $publisher->publish(RunItArtifactFactory::execution('exec-2', 'import', 'internal', 'succeeded'));
        $publisher->publish(RunItArtifactFactory::activity('act-2', 'exec-2', 'parse', 'internal', 'succeeded'));
        $publisher->publish(RunItArtifactFactory::counter('exec-2', 'items', 1));
        $publisher->publish(RunItArtifactFactory::observation('obs-2', 'exec-2', 'info', 'hi'));
        $publisher->publish(RunItArtifactFactory::occurrence('occ-2', 'exec-2', 'execution.started.v1'));
        $publisher->publish(RunItArtifactFactory::snapshot('snap-2', 'exec-2', ['ok' => true]));

        self::assertCount(0, $this->kit->spans());
        self::assertCount(0, $this->kit->collectMetrics());
        self::assertCount(0, $this->kit->logs());
        self::assertFalse($publisher->capability()->any());
    }
}
