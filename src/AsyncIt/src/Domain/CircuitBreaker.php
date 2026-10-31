<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\Port\CircuitState;
use ItPack\AsyncIt\Domain\Exception\CircuitBreakerOpenException;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use Throwable;

/**
 * Circuit breaker that protects downstream services from cascading failures.
 *
 * States: Closed → Open (on failure threshold) → HalfOpen (after cooldown) →
 * Closed (on success) or Open (on failure).
 *
 * Uses the injected {@see MonotonicClockInterface} for all time-based decisions.
 *
 * @see https://w3id.org/itpack/asyncit#CircuitBreaker
 * @see https://w3id.org/itpack/asyncit#CircuitState
 */
final class CircuitBreaker
{
    public readonly int $failureThreshold;

    public readonly DurationInterface $failureTimeout;

    public readonly DurationInterface $cooldown;

    private CircuitState $state = CircuitState::Closed;

    private int $failureCount = 0;

    private ?int $openedAtMonotonic = null;

    public function __construct(
        private readonly MonotonicClockInterface $clock,
        ?int $failureThreshold = null,
        ?DurationInterface $failureTimeout = null,
        ?DurationInterface $cooldown = null,
    ) {
        $this->failureThreshold = $failureThreshold ?? 5;
        $this->failureTimeout = $failureTimeout ?? Duration::ofNanoseconds(60_000_000_000);
        $this->cooldown = $cooldown ?? Duration::ofNanoseconds(10_000_000_000);
    }

    public function state(): CircuitState
    {
        $this->checkCooldownTransition();

        return $this->state;
    }

    public function canExecute(): bool
    {
        $this->checkCooldownTransition();

        return $this->state !== CircuitState::Open;
    }

    public function allowExecution(callable $operation): mixed
    {
        if (!$this->canExecute()) {
            throw new CircuitBreakerOpenException();
        }

        try {
            $result = $operation();
            $this->onSuccess();

            return $result;
        } catch (Throwable $throwable) {
            $this->onFailure();
            throw $throwable;
        }
    }

    private function checkCooldownTransition(): void
    {
        if ($this->state !== CircuitState::Open) {
            return;
        }

        if ($this->openedAtMonotonic === null) {
            return;
        }

        $elapsed = $this->clock->monotonicNanoseconds() - $this->openedAtMonotonic;
        if ($elapsed >= $this->cooldown->nanoseconds()) {
            $this->state = CircuitState::HalfOpen;
            $this->failureCount = 0;
        }
    }

    private function onSuccess(): void
    {
        if ($this->state === CircuitState::HalfOpen) {
            $this->state = CircuitState::Closed;
        }

        $this->failureCount = 0;
    }

    private function onFailure(): void
    {
        $this->failureCount++;

        if ($this->state === CircuitState::HalfOpen) {
            $this->transitionToOpen();

            return;
        }

        if ($this->failureCount >= $this->failureThreshold) {
            $this->transitionToOpen();
        }
    }

    private function transitionToOpen(): void
    {
        $this->state = CircuitState::Open;
        $this->openedAtMonotonic = $this->clock->monotonicNanoseconds();
    }
}
