<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Swoole\Worker;

use Closure;
use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\WorkerLifecycleInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionNameInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceTypeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use Throwable;

/**
 * Reports persistent-worker lifecycle events as RunIt occurrences.
 *
 * Each lifecycle event is recorded as an occurrence owned by a dedicated worker-lifecycle
 * execution, which keeps worker events strictly separate from per-request or per-job
 * executions. {@see self::reset()} releases worker-local state and flushes publishers through
 * the supplied hooks, so it is safe to call from a worker reload handler.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class WorkerLifecycleReporter implements WorkerLifecycleInterface
{
    /**
     * @param Closure(): void|null $resetHook releases worker-local state on reload
     * @param Closure(): void|null $flushHook flushes bounded publishers within the shutdown budget
     */
    public function __construct(
        private ExecutionRunnerInterface $runner,
        private ExecutionNameInterface   $name,
        private ExecutionKindInterface   $kind,
        private ?Closure                 $resetHook = null,
        private ?Closure                 $flushHook = null,
    ) {
    }

    public static function create(
        ExecutionRunnerInterface $runner,
        ?Closure $resetHook = null,
        ?Closure $flushHook = null,
    ): self {
        return new self(
            $runner,
            ExecutionName::fromString('worker.lifecycle'),
            ExecutionKind::fromString('worker.lifecycle'),
            $resetHook,
            $flushHook,
        );
    }

    public function start(): void
    {
        $this->emit(OccurrenceType::fromString('itpack.worker.started.v1'));
    }

    public function stop(): void
    {
        $this->emit(OccurrenceType::fromString('itpack.worker.stopped.v1'));
    }

    public function failure(Throwable $throwable): void
    {
        $this->runner->run($this->definition(), function (ExecutionScopeInterface $scope) use ($throwable): void {
            $scope->occurrences()->record(
                OccurrenceType::fromString('itpack.worker.failed.v1'),
                'worker',
                ['message' => $throwable->getMessage()],
            );
            $scope->observations()->record(
                'Worker failed: ' . $throwable->getMessage(),
                null,
                null,
                $throwable,
                'worker.failure',
            );
        });
    }

    public function reset(): void
    {
        if ($this->resetHook !== null) {
            ($this->resetHook)();
        }

        if ($this->flushHook !== null) {
            ($this->flushHook)();
        }
    }

    private function emit(OccurrenceTypeInterface $type): void
    {
        $this->runner->run($this->definition(), static function (ExecutionScopeInterface $scope) use ($type): void {
            $scope->occurrences()->record($type, 'worker');
        });
    }

    private function definition(): ExecutionDefinitionInterface
    {
        return ExecutionDefinition::create($this->name, $this->kind);
    }
}
