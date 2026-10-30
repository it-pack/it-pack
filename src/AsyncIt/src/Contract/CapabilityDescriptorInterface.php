<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;

interface CapabilityDescriptorInterface extends ValueObjectInterface
{
    public function statusFor(CapabilityKind $kind): CapabilityStatus;
}
