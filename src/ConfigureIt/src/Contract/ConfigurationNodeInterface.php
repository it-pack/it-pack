<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * A single logical configuration value within the resolved graph.
 *
 * A node preserves relationships and metadata beyond a plain array value:
 * source, priority, provenance, schema and validation status.
 *
 * @see docs/architecture/GRAPH.md "Node Model"
 */
interface ConfigurationNodeInterface
{
    public function key(): string;

    /**
     * @return mixed the resolved value of this node.
     */
    public function value(): mixed;

    public function source(): string;

    public function priority(): int;

    /**
     * @return array<string, mixed> node metadata (provenance, schema, origin, ...).
     */
    public function metadata(): array;
}
