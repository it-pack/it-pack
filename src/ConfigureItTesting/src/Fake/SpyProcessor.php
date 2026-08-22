<?php

declare(strict_types=1);

namespace ItPack\ConfigureItTesting\Fake;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;

/**
 * Pass-through {@see ConfigurationProcessorInterface} that records every graph
 * it is asked to process, so tests can assert on pipeline wiring.
 */
final class SpyProcessor implements ConfigurationProcessorInterface
{
    /** @var list<ConfigurationGraphInterface> */
    private array $seen = [];

    public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
    {
        $this->seen[] = $graph;

        return $graph;
    }

    /** @return list<ConfigurationGraphInterface> */
    public function processed(): array
    {
        return $this->seen;
    }

    public function timesCalled(): int
    {
        return count($this->seen);
    }

    public function lastGraph(): ?ConfigurationGraphInterface
    {
        return [] === $this->seen ? null : $this->seen[count($this->seen) - 1];
    }
}
