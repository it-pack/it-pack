<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use function basename;
use function is_scalar;

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
    public function __construct(
        private RedactingRenderer $renderer = new RedactingRenderer(),
    ) {
    }

    public function diff(ConfigurationGraphInterface $base, ConfigurationGraphInterface $other): ConfigurationDiff
    {
        $added = [];
        $removed = [];
        $changed = [];
        $addedSensitive = [];
        $removedSensitive = [];

        foreach ($other->nodes() as $key => $node) {
            if (!$base->has($key)) {
                $added[$key] = $node->value();
                $addedSensitive[$key] = $node->provenance()->isSensitive();

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
                    'sensitive'      => $node->provenance()->isSensitive(),
                ];
            }
        }

        foreach ($base->nodes() as $key => $node) {
            if (!$other->has($key)) {
                $removed[$key] = $node->value();
                $removedSensitive[$key] = $node->provenance()->isSensitive();
            }
        }

        return new ConfigurationDiff($added, $removed, $changed, $addedSensitive, $removedSensitive);
    }

    /**
     * Human-readable summary that masks secret values via the central
     * {@see RedactingRenderer} (M4 — Epic E5.2). Sensitive added/removed/changed
     * values are rendered as the mask regardless of their clear text.
     */
    public function summarize(ConfigurationDiff $diff): string
    {
        if ($diff->empty()) {
            return 'Configuration graphs are identical.';
        }

        $lines = ['Configuration diff:'];

        if ([] !== $diff->added) {
            $lines[] = '  added:';
            foreach ($diff->added as $key => $value) {
                $lines[] = sprintf(
                    '    + %s = %s',
                    $key,
                    $this->renderer->render($value, $diff->addedSensitive[$key] ?? false),
                );
            }
        }

        if ([] !== $diff->removed) {
            $lines[] = '  removed:';
            foreach ($diff->removed as $key => $value) {
                $lines[] = sprintf(
                    '    - %s = %s',
                    $key,
                    $this->renderer->render($value, $diff->removedSensitive[$key] ?? false),
                );
            }
        }

        if ([] !== $diff->changed) {
            $lines[] = '  changed:';
            foreach ($diff->changed as $entry) {
                $toSrc = is_scalar($entry['toProvenance']['resourceUri'] ?? null)
                    ? basename((string) $entry['toProvenance']['resourceUri'])
                    : '?';
                $fromSrc = is_scalar($entry['fromProvenance']['resourceUri'] ?? null)
                    ? basename((string) $entry['fromProvenance']['resourceUri'])
                    : '?';

                $lines[] = sprintf(
                    '    ~ %s: %s -> %s (%s overrode %s)',
                    $entry['key'],
                    $this->renderer->render($entry['from'], $entry['sensitive']),
                    $this->renderer->render($entry['to'], $entry['sensitive']),
                    $toSrc,
                    $fromSrc,
                );
            }
        }

        return implode("\n", $lines);
    }
}
