<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;

/**
 * A named, ordered, conditionally-active bundle of configuration sources.
 *
 * Layers replace implicit "last source wins by discovery order" with explicit,
 * explainable composition: {@see ConfigurationBuilder::addLayer()} orders layers
 * by {@see self::precedence()} before the pipeline runs, so base → environment →
 * local → override becomes declarative. An optional {@see self::match()} predicate
 * gates the whole layer against the {@see ExecutionContextInterface} (env/tenant/
 * profile), enabling environments and profiles.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §3 "Layered Composition & Environments"
 */
interface ConfigurationLayerInterface
{
    /**
     * Sources this layer contributes, in the order they should be merged.
     *
     * @return list<ConfigurationSourceInterface>
     */
    public function sources(): array;

    /**
     * Numeric precedence. Layers are ordered ascending; higher precedence layers
     * are merged later and therefore override lower precedence layers.
     */
    public function precedence(): int;

    /**
     * Stable identifier of this layer, surfaced in node provenance and the
     * inspector/CLI (`explain`).
     */
    public function id(): string;

    /**
     * Whether this layer participates in the current resolution.
     *
     * Used for env/tenant/profile gating; a layer that does not match is skipped
     * entirely (none of its sources are resolved).
     */
    public function match(ExecutionContextInterface $context): bool;
}
