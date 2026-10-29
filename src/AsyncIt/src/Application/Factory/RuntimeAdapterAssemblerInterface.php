<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\CapabilityDescriptor;
use ItPack\AsyncIt\Contract\Port\RuntimeAdapterKind;

/**
 * Assembles the adapter-backed primitives (poller, context storage, channel)
 * for a negotiated {@see RuntimeAdapterKind}.
 *
 * Implementations live in `Application\Factory` (allowed to depend on
 * `Adapter\*`), so the SDK composition root never imports concrete adapters
 * directly — it depends only on this interface.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
interface RuntimeAdapterAssemblerInterface
{
    public function assemble(RuntimeAdapterKind $kind, CapabilityDescriptor $descriptor): RuntimeAssembly;
}
