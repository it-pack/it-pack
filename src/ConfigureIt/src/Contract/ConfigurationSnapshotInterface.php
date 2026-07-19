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
     * @return array<string, mixed> the entire resolved configuration tree.
     */
    public function all(): array;
}
