<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;

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
     */
    public function __construct(
        private array $tree,
    ) {
    }

    public static function fromGraph(ConfigurationGraphInterface $graph): self
    {
        return new self($graph->toArray());
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
}
