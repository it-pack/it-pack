<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Discovery;

use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\CapabilityObserverInterface;
use ItPack\AsyncIt\Contract\RuntimeDiscoveryInterface;

/**
 * Reference runtime discovery: returns the negotiated {@see CapabilityDescriptor}
 * it was built with.
 *
 * The descriptor — not `extension_loaded(...)` — is the single source of truth
 * for which the runtime assembly runs. Phase 4 adds native-adapter branches behind
 * the same contract.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 * @api
 */
final class ReferenceRuntimeDiscovery implements RuntimeDiscoveryInterface
{
    private ?CapabilityObserverInterface $observer = null;

    public function __construct(private readonly CapabilityDescriptorInterface $descriptor)
    {
    }

    public function discover(): CapabilityDescriptorInterface
    {
        return $this->descriptor;
    }

    public function observe(CapabilityObserverInterface $observer): void
    {
        $this->observer = $observer;
    }

    /**
     * Notifies the registered observer of a capability change.
     *
     * Real runtime adapters call this when capabilities evolve (e.g., native
     * channels became unavailable). The reference implementation exposes it
     * for testing; in normal operation no notification is emitted.
     */
    public function notifyChanged(CapabilityDescriptorInterface $descriptor): void
    {
        $this->observer?->onCapabilitiesChanged($descriptor);
    }
}
