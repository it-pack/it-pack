<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ProvenanceInterface;
use ItPack\ConfigureIt\Exception\TypeException;

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

    public function exists(string $path): bool
    {
        return $this->has($path);
    }

    public function int(string $path, ?int $default = null): int
    {
        $value = $this->resolve($path, $default);

        return ScalarCaster::toInt($value, $path);
    }

    public function float(string $path, ?float $default = null): float
    {
        $value = $this->resolve($path, $default);

        return ScalarCaster::toFloat($value, $path);
    }

    public function bool(string $path, ?bool $default = null): bool
    {
        $value = $this->resolve($path, $default);

        return ScalarCaster::toBool($value, $path);
    }

    public function string(string $path, ?string $default = null): string
    {
        $value = $this->resolve($path, $default);

        return ScalarCaster::toString($value, $path);
    }

    /**
     * @param array<int, mixed>|null $default
     */
    public function list(string $path, ?array $default = null): array
    {
        $value = $this->resolve($path, $default);

        /** @var array<int, mixed> $list */
        $list = ScalarCaster::toList($value, $path);

        return $list;
    }

    /**
     * @param array<int, string>|null $default
     */
    public function stringList(string $path, ?array $default = null): array
    {
        $value = $this->resolve($path, $default);

        /** @var array<int, mixed> $list */
        $list = ScalarCaster::toList($value, $path);

        $result = [];

        foreach ($list as $index => $item) {
            $result[$index] = ScalarCaster::toString($item, $path . '[' . $index . ']');
        }

        return $result;
    }

    /**
     * Resolve a typed read: return the default when the key is absent and a
     * default was supplied, otherwise fetch (throwing on a missing required key).
     */
    private function resolve(string $path, mixed $default): mixed
    {
        if ($default !== null || $this->has($path)) {
            return $this->get($path, $default);
        }

        throw TypeException::missing($path);
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
