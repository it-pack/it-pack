<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Vocabulary constants for the negotiated capability model.
 *
 * Two closed sets are captured:
 *  - capability *statuses* (how a runtime provides a feature): `STATUS_*`.
 *  - capability *kinds* (the functional questions asked of a runtime): `KIND_*`.
 *
 * The `STATUS_*` literals map to {@see CapabilityStatus} individuals; the
 * `KIND_*` literals map to {@see CapabilityKind} individuals. Kinds are also
 * published as SKOS concepts in the AsyncIt Scheduling Taxonomy.
 *
 * @see https://w3id.org/itpack/asyncit#CapabilityStatus
 * @see https://w3id.org/itpack/asyncit#CapabilityKind
 * @see https://w3id.org/itpack/asyncit#CapabilitySupported
 * @see https://w3id.org/itpack/asyncit#CapabilityEmulated
 * @see https://w3id.org/itpack/asyncit#CapabilityAdapterOnly
 * @see https://w3id.org/itpack/asyncit#CapabilityUnavailable
 * @see https://w3id.org/itpack/asyncit#CapabilityFiberExecution
 * @see https://w3id.org/itpack/asyncit#CapabilityEdgeTriggeredPolling
 * @see https://w3id.org/itpack/asyncit#CapabilityNativeChannels
 * @see https://w3id.org/itpack/asyncit#CapabilityBlockingHook
 * @see https://w3id.org/itpack/asyncit-taxonomy#cap-fiber-execution
 * @see https://w3id.org/itpack/asyncit-taxonomy#cap-edge-triggered-polling
 * @see https://w3id.org/itpack/asyncit-taxonomy#cap-native-channels
 * @see https://w3id.org/itpack/asyncit-taxonomy#cap-blocking-hook-model
 */
interface CapabilityVocabulary
{
    public const STATUS_SUPPORTED = 'supported';
    public const STATUS_EMULATED = 'emulated';
    public const STATUS_ADAPTER_ONLY = 'adapter_only';
    public const STATUS_UNAVAILABLE = 'unavailable';

    public const KIND_FIBER_EXECUTION = 'fiber_execution';
    public const KIND_EDGE_TRIGGERED_POLLING = 'edge_triggered_polling';
    public const KIND_NATIVE_CHANNELS = 'native_channels';
    public const KIND_BLOCKING_HOOK_MODEL = 'blocking_hook_model';
}
