<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\CLI;

use ItPack\CommandIt\Behavior\Domain\InputCoercionBehavior;
use ItPack\CommandIt\Contract\Command\CommandBusInterface;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Port\SerializerInterface;
use ItPack\CommandIt\Domain\Command;
use ItPack\CommandIt\Domain\CommandInput;
use ItPack\CommandIt\Domain\CommandResultStatus;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Projects one CommandIt definition onto a Symfony Console command.
 *
 * `configure()` mirrors the definition's arguments/options into a Symfony
 * {@see InputDefinition}; `execute()` translates the Symfony input into a
 * CommandIt {@see CommandInputInterface} (reusing the shared coercion behavior),
 * dispatches through the {@see CommandBusInterface}, and writes the
 * {@see \ItPack\CommandIt\Contract\Command\CommandResultInterface} to the console
 * stream. Failures are reported on stderr when the output supports it (with a
 * non-zero exit code); the original exception is preserved by the executor.
 *
 * Result payloads are never dumped raw: objects (and object-bearing arrays) are
 * refused unless a {@see SerializerInterface} is supplied that can normalize
 * them, so handler payloads cannot leak public state to the terminal.
 *
 * @api
 */
final class ConsoleCommandBridge extends SymfonyCommand
{
    use InputCoercionBehavior;

    private const int MAX_UNSAFE_SCAN_DEPTH = 16;

    public function __construct(
        private CommandDefinitionInterface $definition,
        private CommandBusInterface $bus,
        private ?SerializerInterface $serializer = null,
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
            $this->errorOutput($output)->writeln(\sprintf('<error>%s</error>', $throwable->getMessage()));

            return SymfonyCommand::FAILURE;
        }

        if (!$this->writeResult($output, $result->payload())) {
            return SymfonyCommand::FAILURE;
        }

        return $result->status() === CommandResultStatus::Succeeded
            ? SymfonyCommand::SUCCESS
            : SymfonyCommand::FAILURE;
    }

    private function errorOutput(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }

    /**
     * Renders the payload; false signals refusal (exit FAILURE) for data that
     * cannot be printed safely.
     */
    private function writeResult(OutputInterface $output, mixed $payload): bool
    {
        if (\is_object($payload)) {
            return $this->writeObjectPayload($output, $payload);
        }

        if (\is_array($payload)) {
            return $this->writeArrayPayload($output, $payload);
        }

        if ($payload !== null) {
            $output->writeln(\is_scalar($payload) ? (string) $payload : '');
        }

        return true;
    }

    private function writeObjectPayload(OutputInterface $output, object $payload): bool
    {
        if ($this->serializer === null) {
            return $this->refusePayload($output, 'unserializable payload');
        }

        try {
            $normalized = $this->serializer->normalize($payload);
        } catch (\Throwable) {
            return $this->refusePayload($output, 'unserializable payload');
        }

        if (\is_object($normalized)) {
            return $this->refusePayload($output, 'unserializable payload');
        }

        if (\is_array($normalized)) {
            return $this->writeArrayPayload($output, $normalized);
        }

        return $this->writeEncoded($output, $normalized);
    }

    /**
     * @param array<mixed> $payload
     */
    private function writeArrayPayload(OutputInterface $output, array $payload): bool
    {
        if ($this->serializer !== null) {
            try {
                $normalized = $this->serializer->normalize($payload);
            } catch (\Throwable) {
                return $this->refusePayload($output, 'unserializable payload');
            }

            if (\is_object($normalized)) {
                return $this->refusePayload($output, 'unserializable payload');
            }

            return $this->writeEncoded($output, $normalized);
        }

        if ($this->containsObject($payload)) {
            return $this->refusePayload($output, 'unserializable payload');
        }

        return $this->writeEncoded($output, $payload);
    }

    private function writeEncoded(OutputInterface $output, mixed $payload): bool
    {
        try {
            $encoded = \json_encode($payload, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->refusePayload($output, 'unencodable payload');
        }

        $output->writeln($encoded);

        return true;
    }

    private function refusePayload(OutputInterface $output, string $reason): bool
    {
        $this->errorOutput($output)->writeln(
            \sprintf('<error>{"error":"%s"}</error>', $reason)
        );

        return false;
    }

    /**
     * @param array<mixed> $payload
     */
    private function containsObject(array $payload, int $depth = 0): bool
    {
        if ($depth >= self::MAX_UNSAFE_SCAN_DEPTH) {
            return true;
        }

        foreach ($payload as $value) {
            if (\is_object($value)) {
                return true;
            }

            if (\is_array($value) && $this->containsObject($value, $depth + 1)) {
                return true;
            }
        }

        return false;
    }
}
