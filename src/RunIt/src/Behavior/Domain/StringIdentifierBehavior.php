<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;

/**
 * Shared projection and equality behavior for string-backed identifiers.
 *
 * Equality is type-aware: two identifiers are equal when they are the same
 * concrete identifier type and carry the same value. An execution identifier
 * therefore never equals an activity identifier that happens to wrap the same
 * string. Object identity is never consulted.
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
trait StringIdentifierBehavior
{
    abstract public function toString(): string;

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(StringIdentifierInterface $other): bool
    {
        return StringValueEquality::equals($this, $other);
    }
}
