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
            ObservationSeverity::Trace->label() => 'debug',
            ObservationSeverity::Debug->label() => 'debug',
            ObservationSeverity::Info->label() => 'info',
            ObservationSeverity::Notice->label() => 'notice',
            ObservationSeverity::Warning->label() => 'warning',
            ObservationSeverity::Error->label() => 'error',
            ObservationSeverity::Critical->label() => 'critical',
            ObservationSeverity::Alert->label() => 'alert',
            ObservationSeverity::Emergency->label() => 'emergency',
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
