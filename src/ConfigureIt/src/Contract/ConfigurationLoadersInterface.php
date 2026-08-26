<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * Bundled {@see ConfigurationLoaderInterface} registry.
 *
 * Extracted from {@see \ItPack\ConfigureIt\Adapter\ConfigurationLoaders} so
 * consumers can depend on the contract (the default loader set) instead of the
 * adapter concrete (ADR-0002 boundary).
 */
interface ConfigurationLoadersInterface
{
    /**
     * @return list<ConfigurationLoaderInterface>
     */
    public static function default(): array;
}
