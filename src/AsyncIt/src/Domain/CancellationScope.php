<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
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
 */
final class CancellationScope implements CancellationScopeInterface
{
    private bool $cancelled = false;

    /**
     * @var list<callable>
     */
    private array $callbacks = [];

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function throwIfCancelled(): void
    {
        if ($this->cancelled) {
            throw new CancellationException();
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

    public function cancel(): void
    {
        if ($this->cancelled) {
            return;
        }

        $this->cancelled = true;
        foreach ($this->callbacks as $callback) {
            $callback();
        }
    }
}
