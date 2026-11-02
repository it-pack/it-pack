<?php

declare(strict_types=1);

namespace ItPack\ConfigureItPsr11;

use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Wiring\Configurations;
use ItPack\ConfigureIt\Wiring\DefaultConfigurationWiring;

/**
 * Ergonomic factories for {@see ConfigureItContainer}.
 *
 * Two construction styles are supported:
 *
 *  - {@see fromSnapshot()} when you already hold a resolved snapshot (the common
 *    case once a {@see ConfigurationBuilder} has run elsewhere, e.g. in a
 *    framework boot sequence).
 *  - {@see fromBuilder()} when the container should own the final resolve step.
 */
final class ConfigureItContainerFactory
{
    public static function fromSnapshot(
        ConfigurationSnapshotInterface $snapshot,
        string $entryId = ConfigurationSnapshotInterface::class,
    ): ConfigureItContainer {
        return new ConfigureItContainer($snapshot, $entryId);
    }

    public static function fromBuilder(
        ConfigurationBuilder $builder,
        string $entryId = ConfigurationSnapshotInterface::class,
    ): ConfigureItContainer {
        if (! $builder->hasWiring()) {
            $builder = $builder->withWiring(new DefaultConfigurationWiring());
        }

        return new ConfigureItContainer($builder->build()->resolve(), $entryId);
    }

    public static function wiredBuilder(): ConfigurationBuilder
    {
        return Configurations::builder();
    }
}
