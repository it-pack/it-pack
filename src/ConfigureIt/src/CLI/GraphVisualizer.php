<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\CLI;

use function array_keys;
use function basename;
use function count;
use function is_array;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Core\RedactingRenderer;

use function range;
use function sprintf;
use function str_repeat;
use function str_replace;

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
    public function __construct(
        private RedactingRenderer $renderer = new RedactingRenderer(),
    ) {
    }

    public function toDot(ConfigurationGraphInterface $graph): string
    {
        $lines = ['digraph configuration {', '  node [shape=record, fontsize=10];'];

        foreach ($graph->nodes() as $node) {
            $p = $node->provenance();
            $label = sprintf(
                '%s | value: %s | %s',
                $node->key(),
                $this->escape($this->renderer->render($node->value(), $p->isSensitive())),
                basename($p->resourceUri()),
            );

            $lines[] = sprintf(
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
        $sensitive = [];

        foreach ($graph->nodes() as $key => $node) {
            $sensitive[$key] = $node->provenance()->isSensitive();
        }

        return $this->renderTree($graph->toArray(), 0, '', $sensitive);
    }

    /**
     * @param array<mixed, mixed> $tree
     * @param array<string, bool> $sensitive dotted key => is secret
     */
    private function renderTree(array $tree, int $depth = 0, string $prefix = '', array $sensitive = []): string
    {
        $out = '';
        $indent = str_repeat('  ', $depth);

        foreach ($tree as $key => $value) {
            $path = '' === $prefix ? (string) $key : $prefix . '.' . $key;

            if (is_array($value) && [] !== $value && array_keys($value) !== range(0, count($value) - 1)) {
                $out .= $indent . (string) $key . ':'
                    . PHP_EOL . $this->renderTree($value, $depth + 1, $path, $sensitive);

                continue;
            }

            $out .= $indent . (string) $key . ': '
                . $this->renderer->render($value, $sensitive[$path] ?? false) . PHP_EOL;
        }

        return $out;
    }

    private function escape(string $value): string
    {
        return str_replace(['"', "\n", '|'], ['\\"', '\\n', '\\|'], $value);
    }
}
