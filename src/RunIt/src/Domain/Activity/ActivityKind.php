<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Activity;

use ItPack\RunIt\Behavior\Domain\ValidatedStringValueBehavior;
use ItPack\RunIt\Contract\Domain\ActivityKindInterface;

/**
 * The kind or category of an activity.
 *
 * Kinds are extensible strings, not a closed enum.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#activityKind
 * @see https://w3id.org/itpack/runit/taxonomy#ActivityKinds
 */
final readonly class ActivityKind implements ActivityKindInterface
{
    use ValidatedStringValueBehavior;
}
