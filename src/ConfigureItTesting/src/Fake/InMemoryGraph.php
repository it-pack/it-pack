<?php

declare(strict_types=1);

namespace ItPack\ConfigureItTesting\Fake;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\Key;
use ItPack\ConfigureIt\Exception\ConfigurationException;

/**
 * Mutable, in-test {@see ConfigurationGraphInterface} builder.
 *
 * Lets a test assemble a resolved graph from {@see FakeNode}s, query it through
 * the read-only {@see ConfigurationGraphInterface} surface, and freeze it into
 * the immutable core {@see ConfigurationGraph} via {@see build()}.
 */
final class InMemoryGraph implements ConfigurationGraphInterface
{
    /** @var array<string, ConfigurationNodeInterface> */
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

    public function put(ConfigurationNodeInterface $node): void
    {
        $this->nodes[$node->key()] = $node;
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

    public function build(): ConfigurationGraph
    {
        return new ConfigurationGraph($this->nodes);
    }
}
