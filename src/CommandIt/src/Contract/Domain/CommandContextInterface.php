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
    public function has(string $key): bool;

    /**
     * @return mixed
     */
    public function get(string $key): mixed;

    public function classification(string $key): ?Classification;

    public function with(string $key, mixed $value, Classification $classification): self;

    /**
     * Returns an export-safe view: secret values are replaced with `***`.
     *
     * @return array<string, mixed>
     */
    public function redacted(): array;

    /**
     * @return array<string, array{value: mixed, classification: Classification}>
     */
    public function entries(): array;
}
