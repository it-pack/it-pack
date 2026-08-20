<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

/**
 * Server request middleware that wraps each request in one RunIt execution.
 *
 * The middleware builds a request execution, delegates the actual work to the supplied
 * handler (which receives the active {@see ExecutionScopeInterface}), records the outcome
 * (status, duration, unhandled throwable), and ensures request-local state is cleared. It
 * is framework-neutral: the request and response shapes are the consumer's concern.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ServerExecutionMiddlewareInterface
{
    /**
     * @param callable(mixed $request, ExecutionScopeInterface $scope): mixed $handler
     */
    public function handle(mixed $request, callable $handler): mixed;
}
