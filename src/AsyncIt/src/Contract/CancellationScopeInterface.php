<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

/**
 * Observed cooperative cancellation scope.
 *
 * Cancellation in AsyncIt is an *observed state change*, never a preemptive
 * kill. A running task checks {@see self::isCancelled()} at its cooperation
 * points (await boundaries) and unwinds gracefully. {@see self::throwIfCancelled()}
 * centralises that check; {@see self::onCancel()} registers a hook run when the
 * scope is cancelled.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 */
interface CancellationScopeInterface
{
    public function isCancelled(): bool;

    public function throwIfCancelled(): void;

    public function onCancel(callable $callback): void;
}
