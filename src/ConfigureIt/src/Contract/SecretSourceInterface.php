<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * A configuration source that yields secret values (Vault, cloud secret
 * managers, mounted Kubernetes/Docker secrets, …).
 *
 * Marker port: sources implementing this interface are secret-bearing. The
 * {@see \ItPack\ConfigureIt\Core\DocumentNormalizer} flags every value resolved
 * through a secret scheme as {@see \ItPack\ConfigureIt\Contract\ValueObject\ProvenanceInterface::isSensitive()},
 * and the central {@see \ItPack\ConfigureIt\Core\RedactingRenderer} masks such
 * values on every render surface (M4 — Epic E5.1 / E5.2).
 *
 * Companion packages type-hint this interface when they want "give me the secret
 * sources" without depending on any concrete cloud adapter.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §5 "Secrets & Redaction"
 */
interface SecretSourceInterface extends ConfigurationSourceInterface
{
}
