<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Measurement;

use ItPack\RunIt\Contract\Domain\MeasurementUnitInterface;

/**
 * The curated set of measurement units.
 *
 * Units stay low-cardinality and safe to publish. New units extend this enum rather
 * than becoming free-form strings.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Measurement
 */
enum MeasurementUnit: string implements MeasurementUnitInterface
{
    case Count = 'count';
    case Bytes = 'bytes';
    case Kilobytes = 'kilobytes';
    case Megabytes = 'megabytes';
    case Milliseconds = 'milliseconds';
    case Seconds = 'seconds';
    case Minutes = 'minutes';
    case Percent = 'percent';
    case Ratio = 'ratio';
    case Celsius = 'celsius';

    private const array SYMBOLS = [
        'Count' => '#',
        'Bytes' => 'B',
        'Kilobytes' => 'KB',
        'Megabytes' => 'MB',
        'Milliseconds' => 'ms',
        'Seconds' => 's',
        'Minutes' => 'min',
        'Percent' => '%',
        'Ratio' => 'ratio',
        'Celsius' => '°C',
    ];

    public function label(): string
    {
        return $this->value;
    }

    public function symbol(): string
    {
        return self::SYMBOLS[$this->name];
    }
}
