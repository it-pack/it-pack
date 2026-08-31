<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Domain;

/**
 * The immutable command context.
 *
 * A context is a collection of classified entries keyed by a string. It is
 * immutable: {@see self::with()} returns a new context and never mutates the
 * receiver. Secret values are never exported; {@see self::redacted()} replaces
 * them so callers can safely serialize the context.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
interface CommandContextInterface
{
    /**
     * Mask applied at the classification stage: secret values are replaced with
     * this constant in {@see self::redacted()}. It is always applied (regardless
     * of any {@see \ItPack\CommandIt\Contract\Port\RedactionPolicyInterface}), so
     * ambient/secret context never reaches a reporter in plaintext.
     *
     * @see https://w3id.org/itpack/runit#ExecutionContext
     */
    public const string REDACTED = '***';

    public function has(string $key): bool;

    /**
     * @return mixed
     */
    public function get(string $key): mixed;

    public function classification(string $key): ?Classification;

    public function with(string $key, mixed $value, Classification $classification): self;

    /**
     * Returns an export-safe view: secret values are replaced with {@see self::REDACTED}.
     *
     * @return array<string, mixed>
     */
    public function redacted(): array;

    /**
     * Raw entries, including any {@see Classification::Secret} values.
     *
     * Security: the returned arrays expose secret values in plaintext. This view is
     * for internal/serialization use only. General consumers must use
     * {@see self::redacted()} or {@see self::redactedEntries()} instead.
     *
     * @return array<string, array{value: mixed, classification: Classification}>
     */
    public function entries(): array;

    /**
     * Export-safe view of the entries: secret values are replaced with
     * {@see self::REDACTED}, mirroring {@see self::redacted()}.
     *
     * Unlike {@see self::entries()}, this accessor never returns a raw secret value,
     * so it is the correct choice for any general consumer.
     *
     * @return array<string, mixed>
     */
    public function redactedEntries(): array;
}
