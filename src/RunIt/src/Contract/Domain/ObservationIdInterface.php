<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the identifier of an observation.
 *
 * An observation identifier is unique to exactly one recorded observation. It
 * lets an adapter deduplicate observations that are exported more than once
 * without inspecting the message template or attributes.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#observationId
 */
interface ObservationIdInterface extends StringIdentifierInterface
{
}
