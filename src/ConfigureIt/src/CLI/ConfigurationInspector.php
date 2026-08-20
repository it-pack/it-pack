<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\CLI;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;

/**
 * Renders human-readable explanations of a resolved configuration graph.
 *
 * Powers the CLI `explain` / `inspect` commands and any IDE or debug tooling:
 * per-key provenance ("where did this come from, which loader, which processor,
 * did it override another source?") and a flat table of every resolved node.
 *
 * @see docs/architecture/GRAPH.md "Explainability"
 */
final readonly class ConfigurationInspector
{
    public function explain(ConfigurationGraphInterface $graph, ?string $key = null): string
    {
        if ($key !== null) {
            if (!$graph->has($key)) {
                return sprintf('Key "%s" is not present in the resolved configuration.', $key) . PHP_EOL;
            }

            return $this->explainKey($graph->node($key));
        }

        return $this->renderTable($graph);
    }

    private function explainKey(ConfigurationNodeInterface $node): string
    {
        $p = $node->provenance();

        $lines = [
            $node->key(),
            '  value       : ' . $this->display($node->value()),
            '  source      : ' . $p->resourceUri(),
            '  adapter     : ' . $p->adapter(),
            '  format      : ' . $p->format()->name,
            '  priority    : ' . $p->priority(),
            '  sourceKind  : ' . $p->sourceKind(),
            '  overridden  : ' . ($p->overridden() ? 'yes (overrode ' . ($p->overriddenSource() ?? 'unknown') . ')' : 'no'),
            '  validatedBy : ' . ($p->validatedBy() ?? '-'),
            '  trail       : ' . ($p->processorTrail() === [] ? '-' : implode(' -> ', $p->processorTrail())),
        ];

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function renderTable(ConfigurationGraphInterface $graph): string
    {
        if ($graph->nodes() === []) {
            return 'Resolved configuration is empty.' . PHP_EOL;
        }

        $rows = [];

        foreach ($graph->nodes() as $node) {
            $p = $node->provenance();
            $rows[] = [
                $node->key(),
                $this->display($node->value()),
                \basename($p->resourceUri()),
                $this->shortClass($p->adapter()),
                $p->overridden() ? 'yes' : 'no',
            ];
        }

        return $this->table(
            ['KEY', 'VALUE', 'SOURCE', 'ADAPTER', 'OVERRIDDEN'],
            $rows,
        );
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    private function table(array $headers, array $rows): string
    {
        $widths = [];

        foreach ($headers as $i => $header) {
            $widths[$i] = \strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = \max($widths[$i] ?? 0, \strlen($cell));
            }
        }

        $line = static function (array $cells) use ($widths): string {
            $parts = [];

            foreach ($cells as $i => $cell) {
                $parts[] = \str_pad($cell, $widths[$i] ?? 0);
            }

            return implode('  ', $parts) . PHP_EOL;
        };

        $out = $line($headers)
            . $line(\array_map(static fn (int $width): string => \str_repeat('-', $width), $widths));

        foreach ($rows as $row) {
            $out .= $line($row);
        }

        return $out;
    }

    private function shortClass(string $fqcn): string
    {
        $pos = \strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : \substr($fqcn, $pos + 1);
    }

    private function display(mixed $value): string
    {
        if (\is_array($value)) {
            return (string) \json_encode($value);
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        return (string) \json_encode($value);
    }
}
