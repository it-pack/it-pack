<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the identifier of an occurrence.
 *
 * An occurrence identifier is unique to exactly one immutable fact. Together
 * with the occurrence source it forms the identity a CloudEvents adapter maps
 * to the `id` attribute, so it must remain stable for the lifetime of the
 * recorded occurrence.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#occurrenceId
 */
interface OccurrenceIdInterface extends StringIdentifierInterface
{
}
