<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Vocabulary constants for runtime adapter identifiers.
 *
 * Each constant is the string value bound to a {@see RuntimeAdapterKind} enum
 * case, providing a named, refactor-safe reference for the runtime-adapter kind
 * keys used during capability negotiation.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-reference
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-revolt
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swoole
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swow
 */
interface RuntimeAdapterVocabulary
{
    public const ADAPTER_REFERENCE = 'reference';
    public const ADAPTER_REVOLT = 'revolt';
    public const ADAPTER_SWOOLE = 'swoole';
    public const ADAPTER_SWOW = 'swow';
}
