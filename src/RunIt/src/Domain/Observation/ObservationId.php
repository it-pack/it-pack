<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Observation;

use ItPack\RunIt\Behavior\Domain\ValidatedStringIdentifierBehavior;
use ItPack\RunIt\Contract\Domain\ObservationIdInterface;

/**
 * The identity of one recorded observation.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#observationId
 */
final class ObservationId implements ObservationIdInterface
{
    use ValidatedStringIdentifierBehavior;
}
