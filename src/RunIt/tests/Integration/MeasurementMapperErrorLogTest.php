<?php

declare(strict_types=1);

namespace Tests\Integration;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\Metrics\DefaultMeasurementInstrumentMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Metrics\InstrumentCache;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use RuntimeException;
use Tests\Support\Fixture\OpenTelemetry\OtelTestKit;
use Throwable;

final class MeasurementMapperErrorLogTest extends Unit
{
    public function testFallbackIsPayloadFreeAndDisabledProviderDoesNotReport(): void
    {
        $health = new class () implements InternalHealthReporterInterface {
            public ?DefaultMeasurementInstrumentMapper $mapper = null;

            public int $calls = 0;

            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                ++$this->calls;
                $this->mapper?->mapRedactedMeasurement(['name' => 'nested', 'value' => 'nested-secret']);
                throw new RuntimeException('reporter-secret');
            }
        };
        $kit = new OtelTestKit();
        $mapper = new DefaultMeasurementInstrumentMapper($kit->meterProvider, new InstrumentCache(64), [], $health);
        $health->mapper = $mapper;
        $log = tempnam(sys_get_temp_dir(), 'runit-health-');
        self::assertNotFalse($log);
        $previous = ini_get('error_log');
        ini_set('error_log', $log);
        try {
            $mapper->mapRedactedMeasurement(['name' => 'metric', 'value' => 'payload-secret']);
            $mapper->mapRedactedMeasurement(['name' => 'metric', 'value' => 'payload-secret']);
            new DefaultMeasurementInstrumentMapper(null, new InstrumentCache(64), [])
                ->mapRedactedMeasurement(['name' => 'metric', 'value' => 'secret'])
            ;
            new DefaultMeasurementInstrumentMapper($kit->meterProvider, new InstrumentCache(64), [])
                ->mapRedactedMeasurement(['name' => 'metric', 'value' => 'secret'])
            ;
            $contents = file_get_contents($log);
        } finally {
            ini_set('error_log', (string) $previous);
            unlink($log);
        }

        self::assertSame(2, $health->calls);
        self::assertIsString($contents);
        self::assertSame(3, substr_count($contents, 'Redacted measurement has invalid fields.'));
        self::assertStringNotContainsString('secret', $contents);
        self::assertNull($kit->metric('metric'));
    }
}
