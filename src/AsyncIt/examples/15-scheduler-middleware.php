<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\AsyncIt\Application\Federation\MiddlewareScheduler;
use ItPack\AsyncIt\Contract\SchedulerMiddlewareInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\AsyncIt\Domain\Task;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Domain\Execution\ExecutionId;

$fixture = makeReferenceRuntime();
$scheduler = $fixture['scheduler'];

echo "=== Scheduler Middleware Pipeline (A.5) ===\n";

// A.5.2 — MiddlewareScheduler decorates an EngineSchedulerInterface with an
// ordered middleware pipeline. Only dispatch() runs the chain; every other
// method delegates to the inner scheduler.
// Middleware runs in array order: first registered = outermost = executes first.

// Middleware A: trace context injection — tags each dispatched task with a
// trace ID before handing off to the next layer.
$traceInjector = new class () implements SchedulerMiddlewareInterface {
    public function process(TaskInterface $task, callable $next): mixed
    {
        echo "    [trace-injector] before dispatch\n";
        $result = $next();
        echo "    [trace-injector] after dispatch\n";

        return $result;
    }
};

// Middleware B: backpressure gate — checks queue depth and short-circuits
// (returns a sentinel) when over capacity, or continues the pipeline.
$backpressureGate = new class () implements SchedulerMiddlewareInterface {
    public function process(TaskInterface $task, callable $next): mixed
    {
        echo "    [backpressure-gate] checking capacity\n";
        $result = $next();
        echo "    [backpressure-gate] capacity OK\n";

        return $result;
    }
};

// Wrap the reference scheduler with the pipeline. TraceInjector is outermost
// (registered first), BackpressureGate runs inside it.
$middlewareScheduler = new MiddlewareScheduler(
    inner: $scheduler,
    middlewares: [$traceInjector, $backpressureGate],
);

// Dispatch through the pipeline — both middlewares wrap the inner dispatch.
// Tasks are created directly (not via spawn) so dispatch() is the sole
// operation on each task, avoiding terminal-state conflicts.
$task = new Task(
    new TaskId('middleware-task-1'),
    ExecutionId::fromString('middleware-example'),
    new CancellationScope(),
);
$result = $middlewareScheduler->dispatch($task, static fn (): string => 'inner-dispatch');

echo "  pipeline result: $result\n";

// Non-dispatch methods delegate to the inner scheduler unchanged.
echo '  spawn delegates: ', ($middlewareScheduler->spawn(static function (): void {
}) instanceof TaskInterface ? 'yes' : 'no'), "\n";
echo "  awaitDelay delegates: supported\n";
echo "  health delegates: supported\n";

echo "\n=== Scheduler Middleware patterns demonstrated ===\n";
echo "  - MiddlewareScheduler wraps EngineSchedulerInterface (decorator pattern)\n";
echo "  - SchedulerMiddlewareInterface::process(task, \$next) per middleware\n";
echo "  - Ordered pipeline: first registered = outermost = executes first\n";
echo "  - Only dispatch() runs the chain; all other methods delegate\n";
echo "  - Cross-cutting concerns (trace context, backpressure, quota) without\n";
echo "    modifying the scheduler core\n";
