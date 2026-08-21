<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use Closure;
use ItPack\ConfigureIt\Contract\ConfigurationLayerInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;

/**
 * Default {@see ConfigurationLayerInterface} implementation.
 *
 * Holds a list of sources, a numeric precedence, and an optional match predicate.
 * When no predicate is supplied the layer always matches (unconditional).
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §3 "Layered Composition & Environments"
 */
final readonly class ConfigurationLayer implements ConfigurationLayerInterface
{
    /**
     * @param list<ConfigurationSourceInterface> $sources
     * @param Closure(ExecutionContextInterface): bool|null $match optional gating predicate; null = always match.
     * @param string $id layer identifier; empty defaults to `layer-<precedence>`.
     */
    public function __construct(
        private array  $sources,
        private int    $precedence = 0,
        private ?Closure $match = null,
        private string $id = '',
    ) {
    }

    public function sources(): array
    {
        return $this->sources;
    }

    public function precedence(): int
    {
        return $this->precedence;
    }

    public function id(): string
    {
        return $this->id === '' ? sprintf('layer-%d', $this->precedence) : $this->id;
    }

    public function match(ExecutionContextInterface $context): bool
    {
        return $this->match === null || ($this->match)($context);
    }
}
