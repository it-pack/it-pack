<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Activity;

use ItPack\RunIt\Behavior\Domain\ValidatedStringValueBehavior;
use ItPack\RunIt\Contract\Domain\ActivityNameInterface;

/**
 * The human-readable name of an activity, for example `load.yaml`.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#activityName
 */
final readonly class ActivityName implements ActivityNameInterface
{
    use ValidatedStringValueBehavior;
}
