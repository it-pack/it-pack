<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for a safe, immutable description of a thrown error or exception.
 *
 * A description carries only structural, serializable facts: the throwable
 * class, a bounded message, an integer code, an optional previous cause, and a
 * policy-gated stack trace. It never holds a reference to the original
 * `Throwable`, so immutable artifacts can describe failures without retaining
 * live exceptions or unbounded payloads.
 *
 * The description is deterministic: `toArray()` projects the same primitive
 * map regardless of construction order, and nested previous causes project
 * recursively.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ThrowableDescription
 */
interface ThrowableDescriptionInterface
{
    /**
     * Returns the fully-qualified or short throwable class name.
     */
    public function class(): string;

    /**
     * Returns the safe, length-bounded message.
     */
    public function message(): string;

    /**
     * Returns the captured integer code, or 0 when the throwable has no
     * serializable integer code.
     */
    public function code(): int;

    /**
     * Returns the safe previous-cause description, or null when the throwable
     * has no previous cause or the cause depth limit was reached.
     */
    public function previous(): ?self;

    /**
     * Returns the included stack trace, or null when trace capture is disabled
     * by policy.
     */
    public function trace(): ?string;

    /**
     * Returns a deterministic, recursively-primitive description map.
     *
     * @return array<string, string|int|array<array-key, mixed>|null>
     */
    public function toArray(): array;
}
