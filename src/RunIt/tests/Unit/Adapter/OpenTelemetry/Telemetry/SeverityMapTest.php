<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Telemetry;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SeverityMap;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;

/**
 * @group adapter
 * @group open-telemetry
 */
final class SeverityMapTest extends Unit
{
    public function testEveryRunItSeverityMapsToTheDocumentedOpenTelemetryBand(): void
    {
        $map = new SeverityMap();

        $expected = [
            'trace' => 1,
            'debug' => 5,
            'info' => 9,
            'notice' => 10,
            'warning' => 13,
            'error' => 17,
            'critical' => 21,
            'alert' => 22,
            'emergency' => 23,
        ];

        foreach ($expected as $label => $severityNumber) {
            $severity = ObservationSeverity::from($label);

            self::assertSame($severityNumber, $map->toNumber($severity), $label);
        }
    }

    public function testSeverityNumbersFollowTheOpenTelemetryScale(): void
    {
        $map = new SeverityMap();

        self::assertSame(1, $map->toNumber(ObservationSeverity::Trace));
        self::assertSame(9, $map->toNumber(ObservationSeverity::Info));
        self::assertSame(23, $map->toNumber(ObservationSeverity::Emergency));
    }

    public function testTextProjectionIsTheStableRunItLabel(): void
    {
        $map = new SeverityMap();

        self::assertSame('warning', $map->toText(ObservationSeverity::Warning));
    }
}
