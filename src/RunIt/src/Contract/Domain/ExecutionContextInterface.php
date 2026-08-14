<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an immutable, bounded execution context.
 *
 * A context is a collection of classified entries keyed by a validated string.
 * It is immutable: `with()`, `without()`, and `merge()` return a new context
 * and never mutate the receiver. Lookups, capacity, and collision behavior are
 * defined by the implementation.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
interface ExecutionContextInterface extends \Countable
{
    /**
     * Returns whether an entry exists for the key.
     */
    public function has(string $key): bool;

    /**
     * Returns the entry for the key.
     *
     * @throws \ItPack\RunIt\Contract\Exception\InvalidContextValueException when the key is absent
     */
    public function get(string $key): ContextEntryInterface;

    /**
     * Returns the entry for the key, or null when absent.
     */
    public function find(string $key): ?ContextEntryInterface;

    /**
     * Returns all entries keyed by entry key, without exposing mutable internals.
     *
     * @return array<string, ContextEntryInterface>
     */
    public function entries(): array;

    /**
     * Returns a new context with the entry added or replaced, leaving this one
     * unchanged.
     *
     * @throws \ItPack\RunIt\Contract\Exception\InvalidContextValueException when the context capacity is exceeded
     */
    public function with(ContextEntryInterface $entry): self;

    /**
     * Returns a new context without the key, leaving this one unchanged.
     *
     * Removing an absent key returns an equal context.
     */
    public function without(string $key): self;

    /**
     * Returns a new context combining this one with another.
     *
     * Collision behavior is decided by the supplied policy. The receiver is
     * never mutated.
     */
    public function merge(ExecutionContextInterface $other, ContextCollisionPolicy $policy): self;

    /**
     * Returns a deterministic, recursively-primitive context map keyed by entry
     * key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array;
}
