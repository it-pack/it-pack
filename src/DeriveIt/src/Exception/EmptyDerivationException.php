<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Exception;

use LogicException;

/**
 * Thrown when a derivation produces no surface (no methods, no constants) and the
 * configuration demands that this be treated as an error rather than a silent
 * empty interface.
 */
final class EmptyDerivationException extends LogicException
{
}
