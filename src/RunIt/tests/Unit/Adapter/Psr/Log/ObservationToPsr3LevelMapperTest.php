<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Log;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Log\DefaultObservationToPsr3LevelMapper;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;

final class ObservationToPsr3LevelMapperTest extends Unit
{
    public function testMapsEachSeverityToAPsr3Level(): void
    {
        $mapper = new DefaultObservationToPsr3LevelMapper();

        $expected = [
            ObservationSeverity::Trace->name() => 'debug',
            ObservationSeverity::Debug->name() => 'debug',
            ObservationSeverity::Info->name() => 'info',
            ObservationSeverity::Notice->name() => 'notice',
            ObservationSeverity::Warning->name() => 'warning',
            ObservationSeverity::Error->name() => 'error',
            ObservationSeverity::Critical->name() => 'critical',
            ObservationSeverity::Alert->name() => 'alert',
            ObservationSeverity::Emergency->name() => 'emergency',
        ];

        foreach ($expected as $name => $level) {
            self::assertSame(
                $level,
                $mapper->toLevel(ObservationSeverity::from($name)),
                "severity $name",
            );
        }
    }

    public function testNumericMappingFoldsTraceBelowDebug(): void
    {
        $mapper = new DefaultObservationToPsr3LevelMapper();

        self::assertSame('debug', $mapper->toLevelFromNumeric(50));
        self::assertSame('info', $mapper->toLevelFromNumeric(200));
        self::assertSame('error', $mapper->toLevelFromNumeric(400));
        self::assertSame('emergency', $mapper->toLevelFromNumeric(700));
    }
}
