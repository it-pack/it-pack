<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;

/**
 * Per-runtime factory contract: each adapter package supplies an implementation
 * that constructs its poller, context-storage, and channel.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
interface RuntimeAssemblyFactoryInterface
{
    /**
     * Builds a {@see RuntimeAssembly} for the runtime this factory owns.
     */
    public function create(CapabilityDescriptorInterface $descriptor): RuntimeAssemblyInterface;
}
