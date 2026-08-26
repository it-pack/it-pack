<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;

/**
 * The negotiated capability descriptor — the single source of truth for the
 * active runtime environment.
 *
 * It records, per {@see CapabilityKind}, how the selected runtime provides that
 * feature ({@see CapabilityStatus}). Schedulers and discovery select policy
 * from this descriptor rather than probing or branching on runtime names.
 *
 * @see https://w3id.org/itpack/asyncit#CapabilityDescriptor
 */
final readonly class CapabilityDescriptor
{
    /**
     * @param array<CapabilityKind, CapabilityStatus> $capabilities
     */
    public function __construct(public array $capabilities)
    {
    }

    public function statusFor(CapabilityKind $kind): CapabilityStatus
    {
        if (!isset($this->capabilities[$kind])) {
            return CapabilityStatus::Unavailable;
        }

        return $this->capabilities[$kind];
    }
}
