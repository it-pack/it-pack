<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Federation;

use Closure;
use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\ResultTypedSchedulerInterface;
use ItPack\AsyncIt\Contract\RetryPolicyInterface;
use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\AsyncIt\Contract\SchedulerMiddlewareInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Contract\TaskResultInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use LogicException;

/**
 * Decorator wrapping an {@see EngineSchedulerInterface} with an ordered
 * scheduler middleware pipeline.
 *
 * Every method except {@see self::dispatch()} delegates to the inner
 * scheduler. {@see self::dispatch()} runs the task through the middleware
 * chain before handing off to the inner scheduler's dispatch, enabling
 * cross-cutting concerns (trace context, quota enforcement, backpressure,
 * context attachment) without modifying the scheduler core.
 *
 * Middleware runs in array order: the first registered middleware is the
 * outermost layer and executes first.
 *
 * @see https://w3id.org/itpack/asyncit#SchedulerMiddleware
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
final readonly class MiddlewareScheduler implements EngineSchedulerInterface, ResultTypedSchedulerInterface
{
    private Closure $chain;

    /**
     * @param list<SchedulerMiddlewareInterface> $middlewares Ordered pipeline;
     *        the first entry runs first (outermost layer).
     */
    public function __construct(
        private EngineSchedulerInterface $inner,
        array $middlewares = [],
    ) {
        $this->chain = $this->buildChain($middlewares);
    }

    public function submit(TaskInterface $task, callable $operation): void
    {
        $this->inner->submit($task, $operation);
    }

    public function run(): void
    {
        $this->inner->run();
    }

    public function result(TaskInterface $task): mixed
    {
        return $this->inner->result($task);
    }

    /**
     * Runs the task through the middleware pipeline, then dispatches through
     * the inner scheduler.
     *
     * The chain is built inside-out: the inner scheduler's dispatch is the
     * innermost {@see $next}, and each middleware wraps outward in reverse
     * array order so the first registered middleware executes first.
     */
    public function dispatch(TaskInterface $task, callable $operation): mixed
    {
        $innerDispatch = fn (): mixed => $this->inner->dispatch($task, $operation);

        return ($this->chain)($task, $innerDispatch);
    }

    public function spawn(callable $operation, ?TaskIdInterface $id = null): TaskInterface
    {
        return $this->inner->spawn($operation, $id);
    }

    public function await(TaskInterface $task): mixed
    {
        return $this->inner->await($task);
    }

    public function currentTask(): ?TaskInterface
    {
        return $this->inner->currentTask();
    }

    public function awaitDelay(DurationInterface $duration): void
    {
        $this->inner->awaitDelay($duration);
    }

    public function retry(callable $operation, RetryPolicyInterface $policy): mixed
    {
        return $this->inner->retry($operation, $policy);
    }

    public function health(): SchedulerHealthInterface
    {
        return $this->inner->health();
    }

    public function swapCapabilities(CapabilityDescriptorInterface $descriptor): void
    {
        $this->inner->swapCapabilities($descriptor);
    }

    public function resultTyped(TaskInterface $task): TaskResultInterface
    {
        if (!$this->inner instanceof ResultTypedSchedulerInterface) {
            throw new LogicException('Inner scheduler does not support resultTyped().');
        }

        return $this->inner->resultTyped($task);
    }

    /**
     * Pre-builds the middleware composition structure once so that
     * dispatch() only creates the innermost closure per call instead of
     * one closure per middleware on the scheduler hot path.
     *
     * @param list<SchedulerMiddlewareInterface> $middlewares
     */
    private function buildChain(array $middlewares): Closure
    {
        return function (TaskInterface $task, callable $innerDispatch) use ($middlewares): mixed {
            $next = $innerDispatch;
            for ($i = \count($middlewares) - 1; $i >= 0; $i--) {
                $middleware = $middlewares[$i];
                $currentNext = $next;
                $next = (fn (): mixed => $middleware->process($task, $currentNext));
            }

            return $next();
        };
    }
}
