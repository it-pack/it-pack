<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Shared;

/**
 * Media types used as defaults in artifact serialization vocabulary.
 *
 * Declared as an `interface` (constants only, never instantiated) so it carries
 * no behavioral surface and stays exempt from the project's `requireInterface`
 * rule — mirroring {@see KeyPattern} and `CorrelationKeys`.
 *
 * @internal vocabulary only; no behavioral dependency.
 */
interface MediaTypes
{
    public const string APPLICATION_JSON = 'application/json';
}
