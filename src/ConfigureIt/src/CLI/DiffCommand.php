<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\CLI;

use function count;

use ItPack\ConfigureIt\Core\ConfigurationDiffer;
use ItPack\ConfigureIt\Core\RedactingRenderer;
use ItPack\ConfigureIt\Exception\ResolutionException;

/**
 * CLI `diff` subcommand: explains the difference between two configuration files,
 * attaching provenance to every changed key.
 *
 * @see docs/architecture/GRAPH.md "Graph Diffing"
 */
final readonly class DiffCommand implements CommandInterface
{
    public function __construct(
        private ConfigurationDiffer $differ = new ConfigurationDiffer(new RedactingRenderer()),
    ) {
    }

    public function name(): string
    {
        return 'diff';
    }

    public function run(array $args, Output $output): int
    {
        if (count($args) < 2) {
            $output->writeln('Usage: diff <file-a> <file-b>');

            return 1;
        }

        try {
            $base = GraphFromFile::build($args[0]);
            $other = GraphFromFile::build($args[1]);
        } catch (ResolutionException $e) {
            $output->writeln($e->getMessage());

            return 1;
        }

        $output->write($this->differ->summarize($this->differ->diff($base, $other)));

        return 0;
    }
}
