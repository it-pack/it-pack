<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ProvenanceInterface;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;

/**
 * A single logical configuration value within the resolved graph.
 *
 * A node preserves relationships and metadata beyond its raw value: the source
 * it came from, its merge priority, free-form metadata, and structured
 * {@see ProvenanceInterface} (which adapter loaded it, which processors touched
 * it, whether it overrode another source). This is what later enables
 * diagnostics and explainability (see docs/architecture/GRAPH.md).
 *
 * @see docs/architecture/GRAPH.md "Node Model"
 */
final readonly class ConfigurationNode implements ConfigurationNodeInterface
{
    /**
     * @param mixed $value
     * @param array<string, mixed> $metadata free-form metadata (schema hints, origin, ...).
     * @param ProvenanceInterface|null $provenance explicit provenance; when null a minimal
     *        default is derived from $source / $priority.
     */
    public function __construct(
        private string $key,
        private mixed $value,
        private string $source,
        private int $priority,
        private array $metadata = [],
        private ?ProvenanceInterface $provenance = null,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function provenance(): ProvenanceInterface
    {
        return $this->provenance ?? new Provenance(
            resourceUri: $this->source,
            format: Format::PHP,
            adapter: 'unknown',
            priority: $this->priority,
            sourceKind: 'programmatic',
        );
    }

    /**
     * A copy of this node with a new value, preserving source/priority/metadata/provenance.
     */
    public function withValue(mixed $value): self
    {
        return new self($this->key, $value, $this->source, $this->priority, $this->metadata, $this->provenance);
    }

    /**
     * A copy of this node with replaced provenance, preserving value/metadata.
     */
    public function withProvenance(ProvenanceInterface $provenance): self
    {
        return new self($this->key, $this->value, $this->source, $this->priority, $this->metadata, $provenance);
    }
}
