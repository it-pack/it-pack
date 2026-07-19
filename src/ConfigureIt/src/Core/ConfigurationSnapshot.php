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
        $segments = explode('.', $path);
        $cursor = $this->tree;

        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return $default;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    public function has(string $path): bool
    {
        $segments = explode('.', $path);
        $cursor = $this->tree;

        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return false;
            }

            $cursor = $cursor[$segment];
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->tree;
    }
}
