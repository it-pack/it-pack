<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

use ItPack\RunIt\Contract\Domain\ObservationInterface;

/**
 * Maps a RunIt observation to an OpenTelemetry log record.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
interface ObservationLogMapperInterface
{
    public function map(ObservationInterface $observation): void;

    /**
     * @param array<string, mixed> $normalized
     */
    public function mapRedactedObservation(array $normalized): void;
}
