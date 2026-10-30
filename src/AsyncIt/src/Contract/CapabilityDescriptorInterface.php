<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;

interface CapabilityDescriptorInterface extends ValueObjectInterface
{
    public function statusFor(CapabilityKind $kind): CapabilityStatus;

    /**
     * Monotonic schema version for this descriptor instance.
     *
     * Starts at 1 and increments when the set of recognised
     * {@see CapabilityKind} values changes (adds/removes capabilities).
     * Consumers use this to detect when their cached descriptor may be stale.
     *
     * @see https://w3id.org/itpack/asyncit#CapabilityDescriptor
     */
    public function version(): int;
}
