<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Closed set of capability kinds — the functional questions asked of a runtime.
 *
 * Each kind is a question such as "can you run Fibers natively?" or "do you
 * support edge-triggered polling?". The literals are bound to
 * {@see CapabilityVocabulary} and published as SKOS concepts in the AsyncIt
 * Scheduling Taxonomy.
 *
 * @see https://w3id.org/itpack/asyncit#CapabilityKind
 */
enum CapabilityKind: string
{
    /** @see https://w3id.org/itpack/asyncit#CapabilityFiberExecution */
    case FiberExecution = CapabilityVocabulary::KIND_FIBER_EXECUTION;

    /** @see https://w3id.org/itpack/asyncit#CapabilityEdgeTriggeredPolling */
    case EdgeTriggeredPolling = CapabilityVocabulary::KIND_EDGE_TRIGGERED_POLLING;

    /** @see https://w3id.org/itpack/asyncit#CapabilityNativeChannels */
    case NativeChannels = CapabilityVocabulary::KIND_NATIVE_CHANNELS;

    /** @see https://w3id.org/itpack/asyncit#CapabilityBlockingHook */
    case BlockingHook = CapabilityVocabulary::KIND_BLOCKING_HOOK_MODEL;
}
