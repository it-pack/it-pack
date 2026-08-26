<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Measurement;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;

/**
 * Truth table over the closed measurement-unit vocabulary.
 *
 * @group domain
 */
final class MeasurementUnitTest extends Unit
{
    public function testEveryUnitCarriesItsStableLowercaseLabel(): void
    {
        $expected = [
            'count' => '#',
            'bytes' => 'B',
            'kilobytes' => 'KB',
            'megabytes' => 'MB',
            'milliseconds' => 'ms',
            'seconds' => 's',
            'minutes' => 'min',
            'percent' => '%',
            'ratio' => 'ratio',
            'celsius' => '°C',
        ];

        foreach ($expected as $caseName => $symbol) {
            $unit = MeasurementUnit::from($caseName);

            self::assertSame($caseName, $unit->label(), $caseName);
            self::assertSame($symbol, $unit->symbol(), $caseName);
        }
    }

    public function testLabelsAreTheStableLowercaseVocabulary(): void
    {
        foreach (MeasurementUnit::cases() as $unit) {
            self::assertSame($unit->value, $unit->label());
        }
    }

    public function testLabelsRoundTripThroughFromStringEquivalence(): void
    {
        foreach (MeasurementUnit::cases() as $unit) {
            self::assertSame($unit, MeasurementUnit::from($unit->label()), $unit->label());
        }
    }
}
