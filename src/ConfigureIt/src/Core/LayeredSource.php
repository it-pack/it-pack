<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\LayerAwareSourceInterface;

/**
 * Decorator that tags a source with the id of the composition layer it belongs to.
 *
 * Used by {@see ConfigurationBuilder} when flattening registered layers into the
 * ordered source list, so the resolver can stamp the layer id onto the provenance
 * of every node the source contributes.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §3 "Layered Composition & Environments"
 */
final readonly class LayeredSource implements LayerAwareSourceInterface
{
    public function __construct(
        private ConfigurationSourceInterface $inner,
        private string $layerId,
    ) {
    }

    public function discover(): iterable
    {
        return $this->inner->discover();
    }

    public function layerId(): string
    {
        return $this->layerId;
    }
}
