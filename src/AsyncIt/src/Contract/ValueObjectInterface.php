<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

/**
 * Marker for AsyncIt value objects.
 *
 * Value objects are immutable, externally constructed data holders (ids,
 * handles, descriptors, tokens). They are intentionally exempt from the
 * `ForbidNewOutsideFactoryBuilderRule` (AsyncIt M1): unlike collaborators and
 * services, they carry no behavior and are not subject to dependency-injection
 * factory construction. Exceptions (Throwable) are similarly exempt.
 *
 * @see https://w3id.org/itpack/asyncit#ValueObject
 */
interface ValueObjectInterface
{
}
