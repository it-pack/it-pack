<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Wiring;

use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Core\ConfigurationFactory;

/**
 * Composition-root entry point for building configuration resolvers.
 *
 * ConfigureIt's fluent {@see ConfigurationBuilder} depends only on contracts;
 * the concrete wiring (and the default factory) are supplied here so that
 * {@see ConfigurationBuilder} never imports the composition root (NV9c). Prefer
 * {@see self::builder()} over `new ConfigurationBuilder()` in application code.
 */
final class Configurations
{
    public static function builder(): ConfigurationBuilder
    {
        return new ConfigurationBuilder(
            factory: new ConfigurationFactory(),
            wiring: new DefaultConfigurationWiring(),
        );
    }
}
