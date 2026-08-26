<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\ValueObject;

/**
 * Structural contract for a computed configuration diff.
 *
 * Mirrors {@see \ItPack\ConfigureIt\ValueObject\ConfigurationDiff}; extracted so
 * observability/diagnostics consumers depend on the contract rather than the
 * concrete value object (ADR-0008 — contract-first value objects).
 */
interface ConfigurationDiffInterface
{
    public function empty(): bool;

    /**
     * @return array<string, mixed>
     */
    public function added(): array;

    /**
     * @return array<string, mixed>
     */
    public function removed(): array;

    /**
     * @return list<array{
     *     key:string,
     *     from:mixed,
     *     to:mixed,
     *     fromProvenance:array<string, mixed>,
     *     toProvenance:array<string, mixed>,
     *     sensitive:bool,
     * }>
     */
    public function changed(): array;

    /**
     * @return array<string, bool>
     */
    public function addedSensitive(): array;

    /**
     * @return array<string, bool>
     */
    public function removedSensitive(): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    public function summary(): string;
}
