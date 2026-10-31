<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Federation;

use ItPack\AsyncIt\Contract\SchedulerMiddlewareInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use WeakMap;

/**
 * Injects a federated trace context into each dispatched task.
 *
 * Generates a trace ID via {@see IdentifierGeneratorInterface} and records it
 * against the task for the duration of the pipeline. Observability components
 * can later correlate scheduler events across federation boundaries using this
 * ID. The middleware itself stores no persistent state beyond the WeakMap
 * keyed by task instance.
 *
 * @see https://w3id.org/itpack/asyncit#SchedulerMiddleware
 * @see https://w3id.org/itpack/asyncit#Task
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class TraceContextInjector implements SchedulerMiddlewareInterface
{
    /**
     * @param WeakMap<object, string> $traceIds Trace IDs keyed by task instance.
     */
    public function __construct(private WeakMap $traceIds, private IdentifierGeneratorInterface $identifierGenerator)
    {
    }

    public function process(TaskInterface $task, callable $next): mixed
    {
        $this->traceIds->offsetSet($task, $this->identifierGenerator->generate());

        try {
            return $next();
        } finally {
            $this->traceIds->offsetUnset($task);
        }
    }
}
