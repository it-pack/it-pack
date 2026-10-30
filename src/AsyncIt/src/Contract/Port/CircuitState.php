<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Closed set of circuit breaker states.
 *
 * States transition: Closed → Open (on failure threshold) → HalfOpen (after
 * cooldown) → Closed (on success) or Open (on failure).
 *
 * @see https://w3id.org/itpack/asyncit#CircuitState
 */
enum CircuitState: string
{
    case Closed = CircuitStateVocabulary::STATE_CLOSED;

    case Open = CircuitStateVocabulary::STATE_OPEN;

    case HalfOpen = CircuitStateVocabulary::STATE_HALF_OPEN;
}
