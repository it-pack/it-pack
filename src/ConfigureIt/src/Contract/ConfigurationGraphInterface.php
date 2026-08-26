<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use OutOfBoundsException;

/**
 * The internal semantic representation of resolved configuration.
 *
 * Unlike nested arrays, the graph preserves relationships and per-value
 * metadata. It is implementation-independent and eventually enables
 * diagnostics, provenance, and explainability.
 *
 * @see docs/architecture/GRAPH.md "Node Model"
 */
interface ConfigurationGraphInterface
{
    /**
     * @return array<string, ConfigurationNodeInterface>
     */
    public function nodes(): array;

    /**
     * @throws OutOfBoundsException when the key is not present.
     */
    public function node(string $key): ConfigurationNodeInterface;

    public function has(string $key): bool;

    /**
     * @return array<string, mixed> a plain associative view of all node values.
     */
    public function toArray(): array;
}
