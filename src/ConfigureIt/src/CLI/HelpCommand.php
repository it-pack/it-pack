<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\CLI;

/**
 * CLI `help` subcommand: lists available subcommands.
 *
 * @see docs/architecture/GRAPH.md "Developer Experience"
 */
final readonly class HelpCommand implements CommandInterface
{
    /**
     * @param iterable<CommandInterface> $commands the registered subcommands.
     */
    public function __construct(
        private iterable $commands = [],
    ) {
    }

    public function name(): string
    {
        return 'help';
    }

    public function run(array $args, Output $output): int
    {
        $output->writeln('configure-it — configuration inspection CLI');
        $output->writeln('');
        $output->writeln('Usage: configure-it <command> [arguments]');
        $output->writeln('');
        $output->writeln('Commands:');

        foreach ($this->commands as $command) {
            if ('help' === $command->name()) {
                continue;
            }

            $output->writeln('  ' . $command->name());
        }

        $output->writeln('  help');

        return 0;
    }
}
