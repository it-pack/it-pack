<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Closed set of runtime adapters AsyncIt may bind to.
 *
 * The selected adapter is chosen from a negotiated {@see CapabilityDescriptor},
 * never by probing `extension_loaded(...)`. Each individual is the single
 * authority for "which runtime assembly is active"; the scheduler and ports
 * remain identical across every member.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-reference
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-revolt
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swoole
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swow
 */
enum RuntimeAdapterKind: string
{
    /** @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-reference */
    case Reference = RuntimeAdapterVocabulary::ADAPTER_REFERENCE;

    /** @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-revolt */
    case Revolt = RuntimeAdapterVocabulary::ADAPTER_REVOLT;

    /** @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swoole */
    case Swoole = RuntimeAdapterVocabulary::ADAPTER_SWOOLE;

    /** @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swow */
    case Swow = RuntimeAdapterVocabulary::ADAPTER_SWOW;
}
