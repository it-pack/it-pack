<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Exception;

/**
 * Marker for exceptions raised when an invalid value is supplied.
 *
 * Thrown when a validated concept (identifier, name, kind, unit, attribute, or
 * other value object) cannot accept the given value, for example because it is
 * empty, malformed, out of bounds, or exceeds a maximum length.
 */
interface InvalidValueExceptionInterface extends RunItExceptionInterface
{
}
