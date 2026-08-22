<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer\Query;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Core\ConfigurationDiffer;

/**
 * The graph query API (Epic E9.2 — T9.2.2).
 *
 * Runs a safe, enumerate-driven {@see QueryCriterion} over every node of a
 * resolved graph, returning the matching dotted keys. It also exposes a
 * {@see changes()} helper built on top of {@see ConfigurationDiffer} so the
 * "what differs between two resolved graphs" question is answered by the same
 * service. Both are pure and framework-free.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §9 "Graph query"
 */
final class GraphQuery
{
    public function __construct(
        private QueryParser $parser = new QueryParser(),
        private ConfigurationDiffer $differ = new ConfigurationDiffer(),
    ) {
    }

    /**
     * @return list<string> the dotted keys satisfying the expression, sorted
     */
    public function run(ConfigurationGraphInterface $graph, string $expression): array
    {
        $criterion = $this->parser->parse($expression);
        $keys = [];

        foreach ($graph->nodes() as $key => $node) {
            if ($criterion->matches($node)) {
                $keys[] = $key;
            }
        }

        \sort($keys);

        return $keys;
    }

    /**
     * Keys that differ between two resolved graphs (added, removed or changed).
     *
     * @return list<string> the union of differing keys, sorted
     */
    public function changes(ConfigurationGraphInterface $base, ConfigurationGraphInterface $other): array
    {
        $diff = $this->differ->diff($base, $other);
        $keys = [];

        foreach ($diff->added as $key => $value) {
            $keys[] = (string) $key;
        }

        foreach ($diff->removed as $key => $value) {
            $keys[] = (string) $key;
        }

        foreach ($diff->changed as $entry) {
            $keys[] = (string) $entry['key'];
        }

        \sort($keys);

        return $keys;
    }
}
