<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

use Stringable;

/**
 * Contract for immutable string-backed identifiers.
 *
 * Identifiers are stable opaque strings that identify a domain concept. The
 * concrete identity (UUID, ULID, opaque string, or a pluggable generation
 * policy) is decided by the implementing identifier and its factory; this
 * contract only guarantees:
 *
 *  - a stable, human-readable string projection via {@see self::toString()};
 *  - value equality based on that projection via {@see self::equals()};
 *  - rejection of empty identifiers at construction.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
interface StringIdentifierInterface extends Stringable
{
    /**
     * Returns the canonical string form of this identifier.
     */
    public function toString(): string;

    /**
     * Returns whether this identifier is equal to another one by value.
     *
     * The comparison never relies on object identity.
     */
    public function equals(self $other): bool;
}
