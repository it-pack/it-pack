<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\Port\CircuitState;

/**
 * Circuit breaker surface that protects downstream services from cascading
 * failures.
 *
 * Backs {@see CircuitBreaker} so circuit-breaking remains mockable and
 * interface-driven.
 *
 * @see https://w3id.org/itpack/asyncit#CircuitBreaker
 * @see https://w3id.org/itpack/asyncit#CircuitState
 * @api
 */
interface CircuitBreakerInterface
{
    /**
     * Returns the current circuit state, applying any cooldown transition.
     */
    public function state(): CircuitState;

    /**
     * Whether execution is currently allowed (circuit is not Open).
     */
    public function canExecute(): bool;

    /**
     * Runs the operation, tracking success/failure and tripping the circuit on
     * threshold breaches.
     */
    public function allowExecution(callable $operation): mixed;
}
