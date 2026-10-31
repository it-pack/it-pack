<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Retry backoff strategies.
 *
 * @see https://w3id.org/itpack/asyncit-taxonomy#retry-strategies
 */
enum RetryStrategy: string
{
    case Exponential = ResilienceVocabulary::RETRY_EXPONENTIAL;

    case Fixed = ResilienceVocabulary::RETRY_FIXED;

    case Fibonacci = ResilienceVocabulary::RETRY_FIBONACCI;
}
