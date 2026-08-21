<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * An immutable runtime representation of resolved configuration.
 *
 * Applications consume snapshots. Snapshots never change; if configuration
 * changes, a new snapshot is produced (ADR-0003).
 *
 * @see docs/architecture/MODEL.md "Configuration Snapshot"
 */
interface ConfigurationSnapshotInterface
{
    /**
     * @return mixed the value at the dotted path, or the supplied default when absent.
     */
    public function get(string $path, mixed $default = null): mixed;

    public function has(string $path): bool;

    /**
     * Whether a value exists at the dotted path (alias of {@see has()}).
     */
    public function exists(string $path): bool;

    /**
     * Read an integer at the dotted path.
     *
     * Throws {@see \ItPack\ConfigureIt\Exception\TypeException} when the key is
     * missing (and no default supplied) or the value cannot be represented as an
     * int (numeric strings and whole floats are accepted).
     */
    public function int(string $path, ?int $default = null): int;

    /**
     * Read a float at the dotted path.
     *
     * @throws \ItPack\ConfigureIt\Exception\TypeException
     */
    public function float(string $path, ?float $default = null): float;

    /**
     * Read a bool at the dotted path.
     *
     * @throws \ItPack\ConfigureIt\Exception\TypeException
     */
    public function bool(string $path, ?bool $default = null): bool;

    /**
     * Read a string at the dotted path.
     *
     * @throws \ItPack\ConfigureIt\Exception\TypeException
     */
    public function string(string $path, ?string $default = null): string;

    /**
     * Read a sequential list (integer-indexed array) at the dotted path.
     *
     * @return array<int, mixed>
     * @param array<int, mixed>|null $default
     * @throws \ItPack\ConfigureIt\Exception\TypeException
     */
    public function list(string $path, ?array $default = null): array;

    /**
     * Read a list of strings at the dotted path.
     *
     * @return array<int, string>
     * @param array<int, string>|null $default
     * @throws \ItPack\ConfigureIt\Exception\TypeException
     */
    public function stringList(string $path, ?array $default = null): array;

    /**
     * @return array<string, mixed> the entire resolved configuration tree.
     */
    public function all(): array;

    /**
     * Structural provenance of the resolved node at $path, when available.
     *
     * Enables consumers to ask "where did this value come from?" without
     * reaching back into the resolution graph (Roadmap Phase 6).
     */
    public function provenance(string $path): ?\ItPack\ConfigureIt\Contract\ValueObject\ProvenanceInterface;

    /**
     * Whether the resolved node at $path carries a secret value that must be
     * masked on every render surface (M4 — Epic E5.2).
     */
    public function isSensitive(string $path): bool;
}
