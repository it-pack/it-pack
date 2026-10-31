<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Vocabulary constants for circuit breaker states.
 *
 * @see https://w3id.org/itpack/asyncit#CircuitState
 */
interface CircuitStateVocabulary
{
    public const STATE_CLOSED = 'closed';

    public const STATE_OPEN = 'open';

    public const STATE_HALF_OPEN = 'half_open';
}
