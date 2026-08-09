<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

use ItPack\RunIt\Common\Contract\StringableInterface;

/**
 * Contract for any value that projects to a single canonical string.
 *
 * Names, kinds, measurement names, occurrence types, and snapshot types all
 * share this projection. It is deliberately wider than the identifier contract:
 * a name is not an identity, so it does not promise value uniqueness across
 * the system, only a stable string form and type-aware equality.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionName
 */
interface StringProviderInterface extends StringableInterface
{
}
