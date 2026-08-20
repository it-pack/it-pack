<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ProvenanceInterface;

/**
 * An immutable runtime representation of resolved configuration.
 *
 * Produced by freezing a {@see ConfigurationGraph}. Applications depend on this
 * rather than on mutable builders. Snapshots never change; a configuration
 * change yields a new snapshot (ADR-0003).
 *
 * @see docs/architecture/MODEL.md "Configuration Snapshot"
 */
final readonly class ConfigurationSnapshot implements ConfigurationSnapshotInterface
{
    /**
     * @param array<string, mixed> $tree
     * @param array<string, ProvenanceInterface> $provenance per-key provenance from the graph.
     */
    public function __construct(
        private array $tree,
        private array $provenance = [],
    ) {
    }

    public static function fromGraph(ConfigurationGraphInterface $graph): self
    {
        $provenance = [];

        foreach ($graph->nodes() as $key => $node) {
            $provenance[$key] = $node->provenance();
        }

        return new self($graph->toArray(), $provenance);
    }

    public function get(string $path, mixed $default = null): mixed
    {
        return Key::fromString($path)->get($this->tree, $default);
    }

    public function has(string $path): bool
    {
        return Key::fromString($path)->has($this->tree);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->tree;
    }

    public function provenance(string $path): ?ProvenanceInterface
    {
        return $this->provenance[$path] ?? null;
    }
}
