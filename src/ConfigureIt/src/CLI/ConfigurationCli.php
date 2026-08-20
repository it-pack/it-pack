<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\CLI;

/**
 * The configuration inspection CLI dispatcher.
 *
 * Routes `argv` to a registered {@see CommandInterface} and writes to a single
 * {@see Output} sink. Framework-free and offline: every subcommand is a thin
 * adapter over the Core provenance / diagnostics / diff services (Roadmap Phase 7).
 *
 * @see docs/architecture/GRAPH.md "Developer Experience"
 */
final class ConfigurationCli
{
    /**
     * @param list<CommandInterface> $commands
     */
    public function __construct(
        private array $commands = [],
    ) {
        if ($this->commands === []) {
            $this->commands = [
                new ExplainCommand(),
                new DiffCommand(),
                new GraphCommand(),
                new HelpCommand(),
            ];
        }
    }

    /**
     * @param list<string> $argv the full command-line arguments (incl. program name).
     * @return int exit code (0 = success, 2 = unknown command).
     */
    public function handle(array $argv, ?Output $output = null): int
    {
        $output ??= Output::stdout();

        \array_shift($argv); // drop program name
        $subcommand = \array_shift($argv);

        if ($subcommand === null) {
            $this->help($output);

            return 2;
        }

        if ($subcommand === 'help') {
            new HelpCommand($this->commands)->run([], $output);

            return 0;
        }

        foreach ($this->commands as $command) {
            if ($command->name() === $subcommand) {
                return $command->run($argv, $output);
            }
        }

        $output->writeln(\sprintf('Unknown command: "%s".', $subcommand));

        $this->help($output);

        return 2;
    }

    private function help(Output $output): void
    {
        new HelpCommand($this->commands)->run([], $output);
    }
}
