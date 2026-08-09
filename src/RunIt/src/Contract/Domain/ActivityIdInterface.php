<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the identifier of an activity.
 *
 * An activity identifier is unique to exactly one activity within exactly one
 * execution. Nested activities reference their parent through this identity,
 * which keeps activity trees streamable and free of object ownership.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#activityId
 */
interface ActivityIdInterface extends StringIdentifierInterface
{
}
