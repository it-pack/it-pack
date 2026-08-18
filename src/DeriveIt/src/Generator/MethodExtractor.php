<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Generator\MethodFilterInterface;
use ReflectionClass;
use ReflectionMethod;

/**
 * Extracts the effective method surface of a concrete class and runs it through
 * a composable filter chain (VisibilityFilter, StaticFilter, MagicMethodFilter,
 * ConstructorFilter), built from a DeriveConfig.
 *
 * Flat mode calls ReflectionClass::getMethods() on the concrete class — PHP already
 * resolves parent + trait conflicts, so the result is the effective surface without
 * manual graph walking.
 */
final class MethodExtractor
{
    /** @var list<MethodFilterInterface> */
    private readonly array $filters;

    /**
     * @param MethodFilterInterface ...$filters Explicit chain; when empty, the
     *                                        default chain is derived from $config.
     */
    public function __construct(
        private readonly DeriveConfig $config = new DeriveConfig(),
        MethodFilterInterface ...$filters,
    ) {
        $this->filters = $filters;
    }

    /**
     * @return list<ReflectionMethod>
     */
    public function extract(ReflectionClass $reflection): array
    {
        $methods = $reflection->getMethods($this->config->visibilityMask());

        foreach ($this->resolveFilters() as $filter) {
            $methods = array_values(array_filter(
                $methods,
                static fn (ReflectionMethod $method): bool => $filter->keep($method),
            ));
        }

        return $methods;
    }

    /**
     * @return list<MethodFilterInterface>
     */
    private function resolveFilters(): array
    {
        if ($this->filters !== []) {
            return $this->filters;
        }

        return [
            new VisibilityFilter($this->config->visibilityMask()),
            new StaticFilter($this->config->includeStatic),
            new MagicMethodFilter($this->config->includeMagic),
            new ConstructorFilter($this->config->dropConstruct, $this->config->dropDestruct),
        ];
    }
}
