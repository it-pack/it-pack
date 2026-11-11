<?php

declare(strict_types=1);

namespace Tests\Integration;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SnapshotPayloadPolicy;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use RuntimeException;
use Tests\Support\Fixture\OpenTelemetry\OtelTestKit;

final class TerminalSnapshotRedactionTest extends Unit
{
    /** Characterizes exposure under Full export, not a security guarantee. */
    public function testDefaultRedactionExposesFailedExecutionMessageInFullSnapshotExport(): void
    {
        $attributes = $this->exportFailedSnapshot(false);

        self::assertArrayHasKey('runit.snapshot.payload', $attributes);
        self::assertIsString($attributes['runit.snapshot.payload']);
        $payload = json_decode($attributes['runit.snapshot.payload'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertIsArray($payload['result']);
        self::assertIsArray($payload['result']['throwable']);
        self::assertSame('characterization-secret', $payload['result']['throwable']['message']);
        self::assertArrayNotHasKey('runit.redacted', $attributes);
    }

    /** Configured message and trace keys route the snapshot through redacted export. */
    public function testConfiguredMaskingSuppressesPayloadInRedactedSnapshotExport(): void
    {
        $attributes = $this->exportFailedSnapshot(true);

        self::assertTrue($attributes['runit.redacted']);
        self::assertArrayNotHasKey('runit.snapshot.payload', $attributes);
        self::assertStringNotContainsString('characterization-secret', json_encode($attributes, JSON_THROW_ON_ERROR));
    }

    /** @return array<array-key, mixed> */
    private function exportFailedSnapshot(bool $mask): array
    {
        $kit = new OtelTestKit();
        $config = new BridgeConfigBuilder()
            ->withLoggerProvider($kit->loggerProvider)
            ->withOccurrenceLogging(enabled: true)
            ->withSnapshotPayloadPolicy(SnapshotPayloadPolicy::Full)
            ->build()
        ;
        $builder = RunItBuilder::create()
            ->withPublisher(TelemetryPublisher::fromConfig($config))
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
        ;
        if ($mask) {
            $builder = $builder->withRedactor(new DefaultArtifactRedactor(new ArtifactRedactionConfig(
                sensitiveDataKeys: ['message', 'trace'],
            )));
        }

        $exception = new RuntimeException('characterization-secret');
        try {
            $builder->build()->run(
                ExecutionDefinition::create(ExecutionName::fromString('redaction'), ExecutionKind::fromString('test')),
                static function () use ($exception): never {
                    throw $exception;
                },
            );
            self::fail('The business exception must propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($exception, $runtimeException);
        }

        $snapshots = [];
        foreach ($kit->logs() as $record) {
            if ($record->getEventName() === 'runit.snapshot' && $record->getBody() === 'execution.terminal') {
                $snapshots[] = $record->getAttributes()->toArray();
            }
        }

        self::assertCount(1, $snapshots, 'The terminal snapshot must reach the in-memory OTel exporter.');

        return $snapshots[0];
    }
}
