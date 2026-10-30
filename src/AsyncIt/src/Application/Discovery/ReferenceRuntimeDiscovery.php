<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Discovery;

use ItPack\AsyncIt\Contract\CapabilityDescriptor;
use ItPack\AsyncIt\Contract\RuntimeDiscoveryInterface;

/**
 * Reference runtime discovery: returns the negotiated {@see CapabilityDescriptor}
 * it was built with.
 *
 * The descriptor — not `extension_loaded(...)` — is the single source of truth
 * for which runtime assembly runs. Phase 4 adds native-adapter branches behind
 * the same contract.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
  * @api

  */
final readonly class ReferenceRuntimeDiscovery implements RuntimeDiscoveryInterface
{
    public function __construct(private CapabilityDescriptor $descriptor)
    {
    }

    public function discover(): CapabilityDescriptor
    {
        return $this->descriptor;
    }
}
