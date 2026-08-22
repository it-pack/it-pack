<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Activity;

use ItPack\RunIt\Behavior\Domain\ValidatedStringIdentifierBehavior;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;

/**
 * The identity of one activity within an execution.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#activityId
 */
final readonly class ActivityId implements ActivityIdInterface
{
    use ValidatedStringIdentifierBehavior;
}
