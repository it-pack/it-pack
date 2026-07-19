<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Exception\ConfigurationException;

/**
 * The internal semantic representation of resolved configuration.
 *
 * Builds a flat map of dotted keys to {@see ConfigurationNode} instances. Values
 * from higher-priority sources win on conflict; equal priority keeps the first
 * writer. This is the deterministic merge semantic of the resolution pipeline.
 *
 * @see docs/architecture/GRAPH.md "Node Model"
 */
final class ConfigurationGraph implements ConfigurationGraphInterface
{
    /**
     * @var array<string, ConfigurationNodeInterface>
     */
    private array $nodes = [];

    /**
     * @param iterable<ConfigurationNodeInterface> $nodes
     */
    public function __construct(iterable $nodes = [])
    {
        foreach ($nodes as $node) {
            $this->put($node);
        }
    }

    /**
     * Insert or override a node, honouring priority (higher wins).
     */
    public function put(ConfigurationNodeInterface $node): void
    {
        $key = $node->key();

        if (isset($this->nodes[$key])) {
            $existing = $this->nodes[$key];

            if ($node->priority() < $existing->priority()) {
                return;
            }
        }

        $this->nodes[$key] = $node;
    }

    /**
     * @return array<string, ConfigurationNodeInterface>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    public function node(string $key): ConfigurationNodeInterface
    {
        if (!isset($this->nodes[$key])) {
            throw new ConfigurationException(sprintf('Configuration key "%s" does not exist.', $key));
        }

        return $this->nodes[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->nodes[$key]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $tree = [];

        foreach ($this->nodes as $key => $node) {
            Key::fromString($key)->set($tree, $node->value());
        }

        return $tree;
    }
}
