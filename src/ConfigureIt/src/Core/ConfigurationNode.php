<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;

/**
 * A single logical configuration value within the resolved graph.
 *
 * A node preserves relationships and metadata beyond its raw value: the source
 * it came from, its merge priority, and arbitrary metadata (provenance, schema,
 * origin, override history). This is what later enables diagnostics and
 * explainability (see docs/architecture/GRAPH.md).
 *
 * @see docs/architecture/GRAPH.md "Node Model"
 */
final readonly class ConfigurationNode implements ConfigurationNodeInterface
{
    /**
     * @param mixed $value
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private string $key,
        private mixed $value,
        private string $source,
        private int $priority,
        private array $metadata = [],
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
}
