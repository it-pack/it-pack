<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer\Query;

use InvalidArgumentException;

/**
 * Turns a textual expression into a {@see QueryCriterion}.
 *
 * Grammar (all predicates are enumerated in {@see QueryPredicate}):
 *   source:VALUE        keys whose source matches VALUE
 *   overridden-by:VALUE keys that overrode a node from VALUE
 *   sensitive           keys flagged as secrets
 *   adapter:VALUE       keys loaded by adapter VALUE
 *   layer:VALUE         keys from layer VALUE
 *   format:VALUE        keys of format VALUE
 *   trail:VALUE         keys whose processor trail contains VALUE
 *
 * A bare VALUE with no predicate is treated as `source:VALUE`, so the most
 * common query ("keys sourced from local.json") needs no prefix.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §9 "Graph query"
 */
final class QueryParser
{
    public function parse(string $expression): QueryCriterion
    {
        $expression = \trim($expression);

        if ('' === $expression) {
            throw new InvalidArgumentException('Graph query expression must not be empty.');
        }

        foreach (QueryPredicate::cases() as $predicate) {
            if ($expression === $predicate->value) {
                return new QueryCriterion($predicate, null);
            }

            $prefix = $predicate->value.':';

            if (\str_starts_with($expression, $prefix)) {
                $value = \substr($expression, \strlen($prefix));

                return new QueryCriterion($predicate, '' === $value ? null : $value);
            }
        }

        return new QueryCriterion(QueryPredicate::Source, $expression);
    }
}
