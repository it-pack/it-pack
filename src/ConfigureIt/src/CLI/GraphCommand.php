<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\CLI;

use ItPack\ConfigureIt\Exception\ResolutionException;

/**
 * CLI `graph` subcommand: renders a resolved configuration as Graphviz DOT (default)
 * or a plain-text ASCII tree.
 *
 * @see docs/architecture/GRAPH.md "Graph Visualization"
 */
final readonly class GraphCommand implements CommandInterface
{
    public function __construct(
        private GraphVisualizer $visualizer = new GraphVisualizer(),
    ) {
    }

    public function name(): string
    {
        return 'graph';
    }

    public function run(array $args, Output $output): int
    {
        if ($args === []) {
            $output->writeln('Usage: graph <file> [dot|ascii]');

            return 1;
        }

        $format = $args[1] ?? 'dot';

        try {
            $graph = GraphFromFile::build($args[0]);
        } catch (ResolutionException $e) {
            $output->writeln($e->getMessage());

            return 1;
        }

        $output->write($format === 'ascii' ? $this->visualizer->toAscii($graph) : $this->visualizer->toDot($graph));

        return 0;
    }
}
