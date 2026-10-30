<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Vocabulary constants for resilience strategies (retry, jitter).
 *
 * @see https://w3id.org/itpack/asyncit-taxonomy#retry-strategies
 * @see https://w3id.org/itpack/asyncit-taxonomy#jitter-strategies
 */
interface ResilienceVocabulary
{
    public const RETRY_EXPONENTIAL = 'exponential';

    public const RETRY_FIXED = 'fixed';

    public const RETRY_FIBONACCI = 'fibonacci';

    public const JITTER_NONE = 'none';

    public const JITTER_FULL = 'full';

    public const JITTER_EQUAL = 'equal';
}
