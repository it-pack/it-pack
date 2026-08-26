<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Application\Discovery\ReferenceRuntimeDiscovery;
use ItPack\AsyncIt\Contract\CapabilityDescriptor;
use ItPack\AsyncIt\Contract\RuntimeDiscoveryInterface;

/**
 * Selects the reference runtime assembly from a negotiated
 * {@see CapabilityDescriptor}.
 *
 * The descriptor — never `extension_loaded(...)` — drives the choice; the
 * reference runtime is the deterministic `SelectPoller`-backed assembly used
 * until a native adapter is negotiated in Phase 4.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
final class RuntimeDiscoveryFactory implements RuntimeDiscoveryFactoryInterface
{
    public function create(CapabilityDescriptor $descriptor): RuntimeDiscoveryInterface
    {
        return new ReferenceRuntimeDiscovery($descriptor);
    }
}
