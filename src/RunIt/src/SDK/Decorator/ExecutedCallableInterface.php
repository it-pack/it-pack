<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Decorator;

/**
 * A callable wrapped so every invocation runs inside a RunIt execution.
 *
 * The wrapper preserves the original callable's arguments and return value, and rethrows the
 * exact original throwable on failure. It never reflects on the wrapped callable on the
 * invocation hot path: any definition resolution happens once, when the wrapper is created.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutedCallableInterface
{
    /**
     * Runs the wrapped operation inside an execution and returns its result.
     *
     * @param mixed ...$args arguments forwarded unchanged to the wrapped callable
     */
    public function __invoke(mixed ...$args): mixed;
}
