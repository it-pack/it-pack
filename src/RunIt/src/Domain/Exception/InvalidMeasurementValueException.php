<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Exception;

use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use RuntimeException;

/**
 * Raised when a measurement value is not usable as a quantitative sample.
 *
 * A measurement value must be a finite number; `INF`, `-INF`, and `NAN` cannot
 * be serialized, aggregated, or exported, so they are rejected at construction.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Measurement
 */
final class InvalidMeasurementValueException extends RuntimeException implements InvalidValueExceptionInterface
{
    public static function forNonFiniteValue(): self
    {
        return new self('Measurement value must be finite.');
    }
}
