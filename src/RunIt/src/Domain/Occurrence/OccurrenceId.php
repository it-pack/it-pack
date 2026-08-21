<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Occurrence;

use ItPack\RunIt\Behavior\Domain\ValidatedStringIdentifierBehavior;
use ItPack\RunIt\Contract\Domain\OccurrenceIdInterface;

/**
 * The identity of one recorded occurrence.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#occurrenceId
 */
final class OccurrenceId implements OccurrenceIdInterface
{
    use ValidatedStringIdentifierBehavior;
}
