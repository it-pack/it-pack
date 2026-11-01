<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use Throwable;

/**
 * Typed terminal outcome of a task.
 *
 * Makes success/failure explicit at the type level instead of forcing callers
 * to inspect {@see TaskStatus} and catch exceptions manually. Generic over the
 * success value type via PHPDoc `@template` (PHP 8.4 has no runtime generic
 * enforcement — see {@see \ItPack\AsyncIt\Domain\TaskResult} docblock).
 *
 * @see https://w3id.org/itpack/asyncit#TaskResult
 * @see https://w3id.org/itpack/runit#Observation
 * @api
 */
interface TaskResultInterface
{
    /**
     * Returns true when the task completed successfully.
     */
    public function isSuccess(): bool;

    /**
     * Returns true when the task completed with a failure (failed, cancelled,
     * or timed out).
     */
    public function isFailure(): bool;

    /**
     * Returns the success value, or null when the task did not succeed.
     */
    public function value(): mixed;

    /**
     * Returns the terminal throwable, or null when the task did not fail.
     */
    public function error(): ?Throwable;

    /**
     * Returns the terminal task status.
     */
    public function status(): TaskStatus;

    /**
     * Returns the elapsed monotonic duration from task start to completion.
     */
    public function duration(): DurationInterface;
}
