<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Measurement;

use ItPack\RunIt\Contract\Domain\MeasurementKindInterface;

/**
 * The closed set of measurement kinds.
 *
 * The kind decides the value type: `counter` and `size` require integers, while
 * `gauge`, `duration`, and `ratio` require floating-point values.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Measurement
 */
enum MeasurementKind: string implements MeasurementKindInterface
{
    case Counter = 'counter';
    case Gauge = 'gauge';
    case Duration = 'duration';
    case Size = 'size';
    case Ratio = 'ratio';

    public function label(): string
    {
        return $this->value;
    }
}
