<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * A configuration source that knows which composition layer it belongs to.
 *
 * The builder wraps each layer's sources in a {@see \ItPack\ConfigureIt\Core\LayeredSource}
 * carrying the layer id; the resolver reads {@see self::layerId()} while building
 * documents so the normalizer can stamp the layer onto every resulting node's
 * provenance. Sources that do not implement this interface are treated as
 * layer-less (backward compatible).
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §3 "Layered Composition & Environments"
 */
interface LayerAwareSourceInterface extends ConfigurationSourceInterface
{
    /**
     * The id of the layer this source belongs to, or null when not layered.
     */
    public function layerId(): ?string;
}
