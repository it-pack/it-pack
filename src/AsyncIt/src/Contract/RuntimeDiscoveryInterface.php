<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

/**
 * Discovers the active runtime and exposes its negotiated capability profile.
 *
 * Implementations are selected by {@see RuntimeDiscoveryFactoryInterface} from
 * a {@see CapabilityDescriptor}; they never branch on `extension_loaded(...)`.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 * @api
 */
interface RuntimeDiscoveryInterface
{
    public function discover(): CapabilityDescriptorInterface;

    /**
     * Registers an observer that receives capability changes pushed by the
     * runtime adapter (e.g., when native channels become unavailable).
     *
     * The observer is invoked at the next safe reactor tick, never inline.
     * Implementations that do not support observation are a no-op.
     */
    public function observe(CapabilityObserverInterface $observer): void;

    /**
     * Notifies the registered observer of a capability change.
     *
     * Real runtime adapters call this when capabilities evolve (e.g., native
     * channels become unavailable). The reference implementation exposes it
     * for testing; in normal operation no notification is emitted.
     */
    public function notifyChanged(CapabilityDescriptorInterface $descriptor): void;
}
