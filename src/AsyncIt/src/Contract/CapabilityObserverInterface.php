<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

/**
 * Receives observed capability changes from runtime adapters.
 *
 * Adapters push capability changes (e.g., "native channels became unavailable")
 * through this interface. The scheduler reacts by atomically swapping backends,
 * incrementing the {@see CapabilityDescriptorInterface::version()} monotonically.
 *
 * @see https://w3id.org/itpack/asyncit#CapabilityObserver
 */
interface CapabilityObserverInterface
{
    /**
     * Called by an adapter when the negotiated capabilities change.
     *
     * Implementations must not block; the updated descriptor is delivered
     * asynchronously at the next safe reactor tick.
     */
    public function onCapabilitiesChanged(CapabilityDescriptorInterface $descriptor): void;
}
