<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Closed set of negotiated capability statuses.
 *
 * Answers the question "how does this runtime provide feature X?": natively
 * ({@see self::Supported}), via a userspace fallback ({@see self::Emulated}),
 * only through an adapter shim ({@see self::AdapterOnly}), or not at all
 * ({@see self::Unavailable}). The literals are bound to
 * {@see CapabilityVocabulary}.
 *
 * @see https://w3id.org/itpack/asyncit#CapabilityStatus
 */
enum CapabilityStatus: string
{
    /** @see https://w3id.org/itpack/asyncit#CapabilitySupported */
    case Supported = CapabilityVocabulary::STATUS_SUPPORTED;

    /** @see https://w3id.org/itpack/asyncit#CapabilityEmulated */
    case Emulated = CapabilityVocabulary::STATUS_EMULATED;

    /** @see https://w3id.org/itpack/asyncit#CapabilityAdapterOnly */
    case AdapterOnly = CapabilityVocabulary::STATUS_ADAPTER_ONLY;

    /** @see https://w3id.org/itpack/asyncit#CapabilityUnavailable */
    case Unavailable = CapabilityVocabulary::STATUS_UNAVAILABLE;
}
