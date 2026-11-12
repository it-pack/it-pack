<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Log;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Log\DefaultObservationToPsr3LevelMapper;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DefaultObservationToPsr3LevelMapper::class)]
final class ObservationToPsr3LevelMapperTest extends Unit
{
    public function testMapsEachSeverityToAPsr3Level(): void
    {
        $mapper = new DefaultObservationToPsr3LevelMapper();

        $expected = [
            ObservationSeverity::Trace->value => 'debug',
            ObservationSeverity::Debug->value => 'debug',
            ObservationSeverity::Info->value => 'info',
            ObservationSeverity::Notice->value => 'notice',
            ObservationSeverity::Warning->value => 'warning',
            ObservationSeverity::Error->value => 'error',
            ObservationSeverity::Critical->value => 'critical',
            ObservationSeverity::Alert->value => 'alert',
            ObservationSeverity::Emergency->value => 'emergency',
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

        self::assertSame(ObservationSeverity::Debug->value, $mapper->toLevelFromNumeric(50));
        self::assertSame(ObservationSeverity::Info->value, $mapper->toLevelFromNumeric(200));
        self::assertSame(ObservationSeverity::Error->value, $mapper->toLevelFromNumeric(400));
        self::assertSame(ObservationSeverity::Emergency->value, $mapper->toLevelFromNumeric(700));
    }
}
