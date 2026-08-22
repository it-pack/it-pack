<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Application;

use ItPack\CommandIt\Contract\Application\CommandExecutionCoordinatorInterface;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\CommandIt\Contract\Port\ExecutionContextProviderInterface;
use ItPack\CommandIt\Contract\Port\ExecutionReporterInterface;
use ItPack\CommandIt\Contract\ValueObject\CommandExecutionIdInterface;
use ItPack\CommandIt\Domain\CommandExecution;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\CommandStatus;

/**
 * Default lifecycle coordinator.
 *
 * Transitions the execution record through received → executing → terminal, and
 * emits the stable lifecycle occurrences. Reporter failures are best-effort and
 * never replace the original business exception (the executor owns rethrow).
 *
 * When an {@see ExecutionContextProviderInterface} is supplied, the classified
 * ambient context (redacted) is merged into every emitted occurrence so that
 * middleware- or handler-attached entries flow to reporters without extra wiring.
 *
 * @api
 */
final class CommandExecutionCoordinator implements CommandExecutionCoordinatorInterface
{
    public function __construct(
        private ExecutionReporterInterface $reporter,
        private ?ExecutionContextProviderInterface $context = null,
    ) {
    }

    public function begin(
        CommandDefinitionInterface $definition,
        CommandExecutionIdInterface $id,
        int $startedAt,
    ): ExecutionArtifactInterface {
        $execution = CommandExecution::define($definition, $id, $startedAt)
            ->transition(CommandStatus::Received)
        ;

        $this->reportOccurrence(ExecutionReporterInterface::COMMAND_RECEIVED, $this->withContext([
            'execution_id' => $id->toString(),
            'command' => $definition->name()->toString(),
        ]));

        return $execution;
    }

    public function succeed(
        ExecutionArtifactInterface $execution,
        CommandResultInterface $result,
        int $endedAt,
        int $durationMs,
    ): ExecutionArtifactInterface {
        $completed = $this->toExecution($execution)
            ->transition(CommandStatus::Executing)
            ->complete($result, $endedAt)
        ;

        $this->reportOccurrence(ExecutionReporterInterface::COMMAND_HANDLED, $this->withContext([
            'execution_id' => $completed->id()->toString(),
            'command' => $completed->definition()->name()->toString(),
            'status' => $result->status()->label(),
        ]));
        $this->reportMeasurement('duration_ms', $durationMs);

        return $completed;
    }

    public function fail(
        ExecutionArtifactInterface $execution,
        \Throwable $error,
        int $endedAt,
        int $durationMs,
    ): ExecutionArtifactInterface {
        $failed = CommandResult::fail();
        $completed = $this->toExecution($execution)
            ->transition(CommandStatus::Executing)
            ->complete($failed, $endedAt)
        ;

        $this->reportOccurrence(ExecutionReporterInterface::COMMAND_FAILED, $this->withContext([
            'execution_id' => $completed->id()->toString(),
            'command' => $completed->definition()->name()->toString(),
            'error_class' => $error::class,
            'error_message' => $error->getMessage(),
        ]));
        $this->reportMeasurement('duration_ms', $durationMs);

        return $completed;
    }

    private function toExecution(ExecutionArtifactInterface $execution): CommandExecution
    {
        if (!$execution instanceof CommandExecution) {
            throw new \LogicException('Coordinator requires a CommandExecution artifact.');
        }

        return $execution;
    }

    /**
     * Merges the redacted ambient context into an occurrence's explicit context.
     *
     * The redacted view replaces secret values with `***` before they reach the
     * reporter, so a middleware-attached secret is never exported in plaintext.
     *
     * @param array<string, mixed> $explicit
     * @return array<string, mixed>
     */
    private function withContext(array $explicit): array
    {
        $current = $this->context?->current();

        if ($current === null) {
            return $explicit;
        }

        return array_merge($current->redacted(), $explicit);
    }

    /**
     * Best-effort: a reporter failure must never abort the business flow.
     *
     * @param array<string, mixed> $context
     */
    private function reportOccurrence(string $type, array $context): void
    {
        try {
            $this->reporter->reportOccurrence($type, $context);
        } catch (\Throwable) {
        }
    }

    private function reportMeasurement(string $name, int $value): void
    {
        try {
            $this->reporter->reportMeasurement($name, $value, 'ms');
        } catch (\Throwable) {
        }
    }
}
