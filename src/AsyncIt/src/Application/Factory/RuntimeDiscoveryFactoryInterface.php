<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\CapabilityDescriptor;
use ItPack\AsyncIt\Contract\RuntimeDiscoveryInterface;

/**
 * Selects and builds the runtime discovery assembly from a negotiated
 * {@see CapabilityDescriptor}. The descriptor — never `extension_loaded(...)` —
 * drives which runtime is assembled.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
  * @api

  */
interface RuntimeDiscoveryFactoryInterface
{
    public function create(CapabilityDescriptor $descriptor): RuntimeDiscoveryInterface;
}
