<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer\Query;

/**
 * The set of predicates the graph query API understands.
 *
 * Each predicate narrows the resolved {@see \ItPack\ConfigureIt\Contract\ConfigurationGraphInterface}
 * to the nodes that satisfy it. The language is intentionally tiny and fully
 * enumerate-driven so it can never execute arbitrary code (safe by construction).
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §9 "Graph query"
 */
enum QueryPredicate: string
{
    case Source = 'source';
    case OverriddenBy = 'overridden-by';
    case Sensitive = 'sensitive';
    case Adapter = 'adapter';
    case Layer = 'layer';
    case Format = 'format';
    case Trail = 'trail';

    public function hasValue(): bool
    {
        return self::Sensitive !== $this;
    }

    public function description(): string
    {
        return match ($this) {
            self::Source => 'keys whose source matches VALUE (sourceKind or resource basename)',
            self::OverriddenBy => 'keys that overrode a node coming from VALUE',
            self::Sensitive => 'keys flagged as sensitive / secrets',
            self::Adapter => 'keys loaded by the adapter whose short class matches VALUE',
            self::Layer => 'keys originating from layer VALUE',
            self::Format => 'keys of format VALUE (e.g. json, yaml, toml)',
            self::Trail => 'keys whose processor trail contains VALUE',
        };
    }
}
