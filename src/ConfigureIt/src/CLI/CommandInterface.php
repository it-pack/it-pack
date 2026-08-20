<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\CLI;

/**
 * A single CLI subcommand (explain / diff / graph / inspect / help).
 *
 * @see docs/architecture/GRAPH.md "Developer Experience"
 */
interface CommandInterface
{
    /**
     * The subcommand name used on the command line (e.g. "explain").
     */
    public function name(): string;

    /**
     * @param list<string> $args arguments after the subcommand name.
     * @return int exit code (0 = success, non-zero = error).
     */
    public function run(array $args, Output $output): int;
}
