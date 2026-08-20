<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\CLI;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;

/**
 * Renders a resolved configuration graph for visual inspection.
 *
 * Produces a Graphviz DOT document (renders anywhere `dot` is installed, but the
 * textual source is emitted offline) and a plain-text ASCII tree. Powers the CLI
 * `graph` command and future visual tooling (Roadmap Phase 7).
 *
 * @see docs/architecture/GRAPH.md "Graph Visualization"
 */
final readonly class GraphVisualizer
{
    public function toDot(ConfigurationGraphInterface $graph): string
    {
        $lines = ['digraph configuration {', '  node [shape=record, fontsize=10];'];

        foreach ($graph->nodes() as $node) {
            $p = $node->provenance();
            $label = \sprintf(
                '%s | value: %s | %s',
                $node->key(),
                $this->escape($this->display($node->value())),
                \basename($p->resourceUri()),
            );

            $lines[] = \sprintf(
                '  "%s" [label="%s"%s];',
                $this->escape($node->key()),
                $this->escape($label),
                $p->overridden() ? ', color=red' : '',
            );
        }

        $lines[] = '}';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    public function toAscii(ConfigurationGraphInterface $graph): string
    {
        return $this->renderTree($graph->toArray());
    }

    /**
     * @param array<mixed, mixed> $tree
     */
    private function renderTree(array $tree, int $depth = 0): string
    {
        $out = '';
        $indent = \str_repeat('  ', $depth);

        foreach ($tree as $key => $value) {
            if (\is_array($value) && $value !== [] && \array_keys($value) !== \range(0, \count($value) - 1)) {
                $out .= $indent . (string) $key . ':' . PHP_EOL . $this->renderTree($value, $depth + 1);

                continue;
            }

            $out .= $indent . (string) $key . ': ' . $this->display($value) . PHP_EOL;
        }

        return $out;
    }

    private function escape(string $value): string
    {
        return \str_replace(['"', "\n", '|'], ['\\"', '\\n', '\\|'], $value);
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
