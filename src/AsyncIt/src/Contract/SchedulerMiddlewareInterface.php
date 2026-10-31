<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

/**
 * Middleware layer decorating a scheduler's task dispatch pipeline.
 *
 * Each middleware receives a {@see TaskInterface} and a callable {@see $next}
 * that continues the pipeline. A middleware may run logic before and/or after
 * invoking {@see $next()}, or short-circuit by returning without calling it.
 *
 * Middleware is composed into an ordered pipeline by {@see
 * \ItPack\AsyncIt\Application\Federation\MiddlewareScheduler}, which wraps an
 * {@see EngineSchedulerInterface} and runs the chain on every {@see
 * EngineSchedulerInterface::dispatch()} call.
 *
 * @see https://w3id.org/itpack/asyncit#SchedulerMiddleware
 * @see https://w3id.org/itpack/asyncit#Task
 * @api
 */
interface SchedulerMiddlewareInterface
{
    /**
     * Process a task as it flows through the dispatch pipeline.
     *
     * Invoke {@see $next()} to hand control to the next middleware (or the
     * underlying scheduler). Return its result, transform it, or short-circuit
     * by returning a different value without calling {@see $next()}.
     *
     * @param TaskInterface $task The task being dispatched.
     * @param callable $next Continue the pipeline; invoking it runs the next
     *        middleware or the scheduler's dispatch.
     * @return mixed The result of the pipeline (ultimately the task's result).
     */
    public function process(TaskInterface $task, callable $next): mixed;
}
