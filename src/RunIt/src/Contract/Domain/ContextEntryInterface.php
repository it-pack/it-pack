<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for a single immutable, classified context entry.
 *
 * An entry binds a validated key to a bounded value, a classification, and
 * optional metadata (an expiry instant and a source identifier). Entries are
 * immutable: queries return the captured data and `toArray()` projects a
 * deterministic, recursively-primitive map.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
interface ContextEntryInterface
{
    /**
     * Returns the entry key.
     */
    public function key(): ContextKeyInterface;

    /**
     * Returns the entry value.
     *
     * @return int|float|string|bool|null|array<int|string, int|float|string|bool|null>
     */
    public function value(): int|float|string|bool|null|array;

    /**
     * Returns the classification that governs this entry's default edge behavior.
     */
    public function classification(): ContextClassificationInterface;

    /**
     * Returns the optional expiry instant, or null when the entry never expires.
     */
    public function expiresAt(): ?InstantInterface;

    /**
     * Returns the optional source identifier, or null when unset.
     */
    public function source(): ?ContextSourceInterface;

    /**
     * Returns whether the entry is expired relative to the given instant.
     *
     * Entries without an expiry are never expired.
     */
    public function isExpired(InstantInterface $now): bool;

    /**
     * Returns a deterministic, recursively-primitive entry map.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
