<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\CLI;

use ItPack\ConfigureIt\Exception\ResolutionException;

/**
 * CLI `explain` subcommand: prints provenance for one key, or a flat table of
 * every resolved node when no key is given.
 *
 * @see docs/architecture/GRAPH.md "Explainability"
 */
final readonly class ExplainCommand implements CommandInterface
{
    public function __construct(
        private ConfigurationInspector $inspector = new ConfigurationInspector(),
    ) {
    }

    public function name(): string
    {
        return 'explain';
    }

    public function run(array $args, Output $output): int
    {
        if ($args === []) {
            $output->writeln('Usage: explain <file> [key]');

            return 1;
        }

        $path = $args[0];
        $key = $args[1] ?? null;

        try {
            $graph = GraphFromFile::build($path);
        } catch (ResolutionException $e) {
            $output->writeln($e->getMessage());

            return 1;
        }

        $output->write($this->inspector->explain($graph, $key));

        return 0;
    }
}
