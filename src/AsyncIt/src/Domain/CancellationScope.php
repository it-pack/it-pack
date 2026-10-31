<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Domain\Exception\CancellationException;

/**
 * Observed, cooperative cancellation scope.
 *
 * Cancellation is an observed state change: a running task calls
 * {@see self::throwIfCancelled()} at its cooperation points and unwinds
 * gracefully. {@see self::onCancel()} registers hooks run when the scope is
 * cancelled. The scope is never used to preemptively kill a Fiber.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 * @see https://w3id.org/itpack/asyncit#hasCancellationReason
 *   @api
 */
final class CancellationScope implements CancellationScopeInterface
{
    private bool $cancelled = false;

    private ?CancellationReason $reason = null;

    /**
     * @var list<callable>
     */
    private array $callbacks = [];

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function cancel(?CancellationReason $reason = null): void
    {
        if ($this->cancelled) {
            return;
        }

        $this->cancelled = true;
        $this->reason = $reason ?? CancellationReason::UserInitiated;

        foreach ($this->callbacks as $callback) {
            $callback();
        }
    }

    public function reason(): ?CancellationReason
    {
        return $this->reason;
    }

    public function throwIfCancelled(): void
    {
        if ($this->cancelled) {
            throw new CancellationException($this->reason ?? CancellationReason::UserInitiated);
        }
    }

    public function onCancel(callable $callback): void
    {
        if ($this->cancelled) {
            $callback();

            return;
        }

        $this->callbacks[] = $callback;
    }
}
