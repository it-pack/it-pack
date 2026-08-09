<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the type of an occurrence.
 *
 * Occurrence types are stable, lowercase, versioned identifiers such as
 * `itpack.configuration.loaded.v1`. Unlike names and kinds they follow a
 * stricter format because they become CloudEvents `type` values and must stay
 * low-cardinality and independent of any PHP class name.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#occurrenceType
 */
interface OccurrenceTypeInterface extends StringValueInterface
{
}
