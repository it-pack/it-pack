<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Contract;

use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;

/**
 * Reconstructs a RunIt occurrence from a CloudEvent.
 *
 * The mapper validates the required CloudEvent fields, rejects malformed input,
 * and rebuilds the RunIt semantic fields. Known RunIt extension attributes are
 * folded into their semantic fields; unknown extension attributes are preserved
 * in the reconstructed occurrence so round trips do not lose information.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 * @see https://cloudevents.io/
 */
interface CloudEventToOccurrenceMapperInterface
{
    /**
     * @throws InvalidCloudEventException when a required field is missing or malformed
     */
    public function map(CloudEvent $event): OccurrenceInterface;
}
