<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Contract\ValueObject\ProvenanceInterface;

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
     * @return array<string, mixed> free-form node metadata (schema, origin, ...).
     */
    public function metadata(): array;

    /**
     * Structural provenance of this node (source, adapter, processor trail,
     * override status). Never null: when a node is constructed without explicit
     * provenance a minimal default is derived from its source/priority.
     */
    public function provenance(): ProvenanceInterface;

    /**
     * A copy of this node with a new value, preserving source/priority/metadata/provenance.
     */
    public function withValue(mixed $value): self;

    /**
     * A copy of this node with replaced provenance, preserving value/metadata.
     */
    public function withProvenance(ProvenanceInterface $provenance): self;
}
