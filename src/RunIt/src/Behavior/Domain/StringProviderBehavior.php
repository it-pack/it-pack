<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

/**
 * Shared projection behavior for any value that exposes a single canonical
 * string.
 *
 * A `StringProviderInterface` is rendered through `toString()` and the native
 * `__toString()` so it can be interpolated and logged directly.
 *
 * @see https://w3id.org/itpack/runit#executionName
 */
trait StringProviderBehavior
{
    abstract public function toString(): string;

    public function __toString(): string
    {
        return $this->toString();
    }
}
