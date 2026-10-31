<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\Port\CancellationReason;

/**
 * Observed cooperative cancellation scope.
 *
 * Cancellation in AsyncIt is an *observed state change*, never a preemptive
 * kill. A running task checks {@see self::isCancelled()} at its cooperation
 * points (await boundaries) and unwinds gracefully. {@see self::throwIfCancelled()}
 * centralises that check; {@see self::onCancel()} registers a hook run when the
 * scope is cancelled.
 *
 * The optional {@see self::cancel()} reason is immutable once set: the first
 * reason recorded on a scope is preserved across subsequent calls.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 * @see https://w3id.org/itpack/asyncit#CancellationReason
 * @api
 */
interface CancellationScopeInterface
{
    public function isCancelled(): bool;

    /**
     * Marks the scope as cancelled and runs registered {@see self::onCancel()} hooks.
     *
     * The reason is recorded immutably: if already cancelled, the original
     * reason is preserved.
     */
    public function cancel(?CancellationReason $reason = null): void;

    /**
     * The reason the scope was cancelled, or null if not yet cancelled.
     *
     * @see https://w3id.org/itpack/asyncit#hasCancellationReason
     */
    public function reason(): ?CancellationReason;

    public function throwIfCancelled(): void;

    public function onCancel(callable $callback): void;
}
