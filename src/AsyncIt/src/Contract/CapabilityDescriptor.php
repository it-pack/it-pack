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
 * The map is keyed by each {@see CapabilityKind} scalar value (backed enum
 * cases cannot be used as PHP array keys), and {@see self::statusFor()} accepts
 * the typed kind.
 *
 * @see https://w3id.org/itpack/asyncit#CapabilityDescriptor
 */
final readonly class CapabilityDescriptor implements ValueObjectInterface
{
    /**
     * @param array<string, CapabilityStatus> $capabilities keyed by CapabilityKind value
     */
    public function __construct(public array $capabilities)
    {
    }

    public function statusFor(CapabilityKind $kind): CapabilityStatus
    {
        return $this->capabilities[$kind->value] ?? CapabilityStatus::Unavailable;
    }
}
