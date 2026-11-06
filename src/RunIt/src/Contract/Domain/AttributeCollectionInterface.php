<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

use Countable;

/**
 * Contract for an immutable, bounded collection of structured attributes.
 *
 * Attributes are key-value metadata where values are limited to scalars, null,
 * and lists/maps of scalar values. The collection is immutable: `with()` and
 * `without()` return a new collection and never mutate the receiver.
 *
 * Implementations enforce a maximum key length and attribute count, and they
 * reject objects and resources so normalization and serialization stay safe.
 * `toArray()` returns a deterministic, recursively-primitive map suitable for
 * direct use as an artifact's attributes.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
interface AttributeCollectionInterface extends Countable
{
    /**
     * Returns the value stored under the key, or null when the key is absent.
     *
     * @return int|float|string|bool|null|array<int|string, int|float|string|bool|null>
     */
    public function get(string $key): int|float|string|bool|null|array;

    /**
     * Returns whether the key is present.
     */
    public function has(string $key): bool;

    /**
     * Returns whether the collection carries no attributes.
     */
    public function isEmpty(): bool;

    /**
     * Returns a new collection with the key set to the value, leaving this one
     * unchanged. Setting an existing key replaces its value.
     */
    public function with(string $key, mixed $value): self;

    /**
     * Returns a new collection without the key, leaving this one unchanged.
     *
     * Removing an absent key returns an equal collection.
     */
    public function without(string $key): self;

    /**
     * Returns a deterministic, bounded, recursively-primitive attribute map.
     *
     * @return array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>>
     */
    public function toArray(): array;
}
