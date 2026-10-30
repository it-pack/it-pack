<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Jitter strategies for retry backoff.
 *
 * @see https://w3id.org/itpack/asyncit-taxonomy#jitter-strategies
 */
enum JitterStrategy: string
{
    case None = ResilienceVocabulary::JITTER_NONE;

    case Full = ResilienceVocabulary::JITTER_FULL;

    case Equal = ResilienceVocabulary::JITTER_EQUAL;
}
