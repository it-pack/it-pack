<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Application;

use ItPack\CommandIt\Contract\Application\CommandExecutionCoordinatorInterface;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Port\ExecutionContextProviderInterface;
use ItPack\CommandIt\Contract\Port\LifecycleReporterInterface;
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
 * The coordinator depends only on {@see LifecycleReporterInterface}: the core pipeline
 * emits occurrences and measurements, never observations or activity boundaries, so a
 * narrow reporter satisfies it (a full {@see \ItPack\CommandIt\Contract\Port\ExecutionReporterInterface}
 * is a structural superset and is what the SDK wires in).
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
        private LifecycleReporterInterface $reporter,
        private ?ExecutionContextProviderInterface $context = null,
    ) {
    }

    public function begin(
        CommandDefinitionInterface $definition,
        CommandExecutionIdInterface $id,
        int $startedAt,
    ): CommandExecution {
        $execution = CommandExecution::define($definition, $id, $startedAt)
            ->transition(CommandStatus::Received)
        ;

        $this->reportOccurrence(LifecycleReporterInterface::COMMAND_RECEIVED, $this->withContext([
            LifecycleReporterInterface::CONTEXT_EXECUTION_ID => $id->toString(),
            LifecycleReporterInterface::CONTEXT_COMMAND => $definition->name()->toString(),
        ]));

        return $execution;
    }

    public function succeed(
        CommandExecution $execution,
        CommandResultInterface $result,
        int $endedAt,
        int $durationMs,
    ): CommandExecution {
        $completed = $execution
            ->transition(CommandStatus::Executing)
            ->complete($result, $endedAt)
        ;

        $this->reportOccurrence(LifecycleReporterInterface::COMMAND_HANDLED, $this->withContext([
            LifecycleReporterInterface::CONTEXT_EXECUTION_ID => $completed->id()->toString(),
            LifecycleReporterInterface::CONTEXT_COMMAND => $completed->definition()->name()->toString(),
            LifecycleReporterInterface::CONTEXT_STATUS => $result->status()->label(),
        ]));
        $this->reportMeasurement(LifecycleReporterInterface::MEASUREMENT_DURATION, $durationMs);

        return $completed;
    }

    public function fail(
        CommandExecution $execution,
        \Throwable $error,
        int $endedAt,
        int $durationMs,
    ): CommandExecution {
        $failed = CommandResult::fail();
        $completed = $execution
            ->transition(CommandStatus::Executing)
            ->complete($failed, $endedAt)
        ;

        $this->reportOccurrence(LifecycleReporterInterface::COMMAND_FAILED, $this->withContext([
            LifecycleReporterInterface::CONTEXT_EXECUTION_ID => $completed->id()->toString(),
            LifecycleReporterInterface::CONTEXT_COMMAND => $completed->definition()->name()->toString(),
            LifecycleReporterInterface::CONTEXT_ERROR_CLASS => $error::class,
            LifecycleReporterInterface::CONTEXT_ERROR_MESSAGE => $error->getMessage(),
        ]));
        $this->reportMeasurement(LifecycleReporterInterface::MEASUREMENT_DURATION, $durationMs);

        return $completed;
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
            $this->reporter->reportMeasurement($name, $value, LifecycleReporterInterface::DURATION_UNIT_MS);
        } catch (\Throwable) {
        }
    }
}
