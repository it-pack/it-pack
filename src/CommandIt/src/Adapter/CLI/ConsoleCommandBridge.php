<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\CLI;

use ItPack\CommandIt\Behavior\Domain\InputCoercionBehavior;
use ItPack\CommandIt\Contract\Command\CommandBusInterface;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Domain\Command;
use ItPack\CommandIt\Domain\CommandInput;
use ItPack\CommandIt\Domain\CommandResultStatus;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Projects one CommandIt definition onto a Symfony Console command.
 *
 * `configure()` mirrors the definition's arguments/options into a Symfony
 * {@see InputDefinition}; `execute()` translates the Symfony input into a
 * CommandIt {@see CommandInputInterface} (reusing the shared coercion behavior),
 * dispatches through the {@see CommandBusInterface}, and writes the
 * {@see \ItPack\CommandIt\Contract\Command\CommandResultInterface} to the console
 * stream. Failures are reported on stderr with a non-zero exit code; the
 * original exception is preserved by the executor and surfaced via the result.
 *
 * @api
 */
final class ConsoleCommandBridge extends SymfonyCommand
{
    use InputCoercionBehavior;

    public function __construct(
        private CommandDefinitionInterface $definition,
        private CommandBusInterface $bus,
    ) {
        parent::__construct($this->definition->name()->toString());
    }

    protected function configure(): void
    {
        $this->setDescription($this->definition->description());

        $arguments = [];
        foreach ($this->definition->arguments() as $argument) {
            $arguments[] = new InputArgument(
                $argument->name()->toString(),
                $argument->required() ? InputArgument::REQUIRED : InputArgument::OPTIONAL,
                $argument->description(),
            );
        }

        $options = [];
        foreach ($this->definition->options() as $option) {
            $mode = $option->type() === 'bool'
                ? InputOption::VALUE_NONE
                : ($option->required() ? InputOption::VALUE_REQUIRED : InputOption::VALUE_OPTIONAL);

            $options[] = new InputOption(
                $option->name()->toString(),
                null,
                $mode,
                $option->description(),
            );
        }

        $this->setDefinition(new InputDefinition([...$arguments, ...$options]));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $arguments = [];
        foreach ($this->definition->arguments() as $argument) {
            $name = $argument->name()->toString();
            $value = $input->getArgument($name);
            if ($value !== null) {
                $arguments[$name] = $value;
            }
        }

        $options = [];
        foreach ($this->definition->options() as $option) {
            $name = $option->name()->toString();
            $value = $input->getOption($name);
            if ($value !== null && $value !== false) {
                $options[$name] = $value;
            }
        }

        $commandInput = new CommandInput(
            $this->resolveInputs($this->definition->arguments(), $arguments, 'argument'),
            $this->resolveInputs($this->definition->options(), $options, 'option'),
        );

        $command = new Command($this->definition->name(), $commandInput);

        try {
            $result = $this->bus->dispatch($command);
        } catch (\Throwable $throwable) {
            $output->writeln(\sprintf('<error>%s</error>', $throwable->getMessage()));

            return SymfonyCommand::FAILURE;
        }

        $this->writeResult($output, $result->payload());

        return $result->status() === CommandResultStatus::Succeeded
            ? SymfonyCommand::SUCCESS
            : SymfonyCommand::FAILURE;
    }

    private function writeResult(OutputInterface $output, mixed $payload): void
    {
        if (\is_array($payload) || \is_object($payload)) {
            $encoded = \json_encode($payload);

            if ($encoded === false) {
                $encoded = 'null';
            }

            $output->writeln($encoded);

            return;
        }

        if ($payload !== null) {
            $output->writeln(\is_scalar($payload) ? (string) $payload : '');
        }
    }
}
