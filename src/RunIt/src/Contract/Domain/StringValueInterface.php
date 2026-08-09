<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an immutable, validated string value such as a name or kind.
 *
 * Unlike {@see StringIdentifierInterface}, a string value is not an identity
 * and is not expected to be globally unique. It does share the guarantees of a
 * stable string projection and type-aware equality, and it rejects malformed
 * input on construction through a {@see StringValueValidatorInterface}.
 *
 * Kinds and occurrence types are intentionally open: consumers supply
 * application-specific values rather than choosing from a closed enum, so the
 * domain never has to change when a new kind appears.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionKind
 */
interface StringValueInterface extends StringProviderInterface
{
    /**
     * Returns whether this value is equal to another one by value.
     *
     * The comparison never relies on object identity and is type-aware: an
     * execution kind never equals an activity kind that happens to wrap the
     * same string.
     */
    public function equals(self $other): bool;
}
