<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Exception;

use LogicException;

/**
 * Thrown when the derived interface short name already collides with a type that
 * exists in the target namespace, so rendering would silently shadow or clash.
 */
final class NamingCollisionException extends LogicException
{
    public static function forFqcn(string $fqcn): self
    {
        return new self(sprintf(
            'Cannot derive interface "%s": a type with that name already exists in the target namespace.',
            $fqcn,
        ));
    }
}
