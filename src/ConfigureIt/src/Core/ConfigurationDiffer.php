<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationDiff;

/**
 * Produces a {@see ConfigurationDiff} between two resolved graphs.
 *
 * Added/removed keys are detected by key set; changed keys by value equality.
 * For every changed key both sides' provenance is attached so the diff explains
 * *why* a value moved. Pure and framework-free (Roadmap Phase 6).
 *
 * @see docs/architecture/GRAPH.md "Graph Diffing"
 */
final readonly class ConfigurationDiffer
{
    public function diff(ConfigurationGraphInterface $base, ConfigurationGraphInterface $other): ConfigurationDiff
    {
        $added = [];
        $removed = [];
        $changed = [];

        foreach ($other->nodes() as $key => $node) {
            if (!$base->has($key)) {
                $added[$key] = $node->value();

                continue;
            }

            $from = $base->node($key)->value();

            if (serialize($from) !== serialize($node->value())) {
                $changed[] = [
                    'key'            => $key,
                    'from'           => $from,
                    'to'             => $node->value(),
                    'fromProvenance' => $base->node($key)->provenance()->toArray(),
                    'toProvenance'   => $node->provenance()->toArray(),
                ];
            }
        }

        foreach ($base->nodes() as $key => $node) {
            if (!$other->has($key)) {
                $removed[$key] = $node->value();
            }
        }

        return new ConfigurationDiff($added, $removed, $changed);
    }
}
