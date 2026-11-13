<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

use ItPack\RunIt\Contract\Domain\MeasurementInterface;

/**
 * Records a RunIt measurement against the appropriate OpenTelemetry instrument.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Measurement
 */
interface MeasurementInstrumentMapperInterface
{
    public function record(MeasurementInterface $measurement): void;

}
