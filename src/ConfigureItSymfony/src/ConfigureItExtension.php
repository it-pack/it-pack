<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSymfony;

use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * Wires ConfigureIt into a Symfony service container.
 *
 * After {@see load()}, the container exposes:
 *
 *  - {@see ConfigurationSnapshotInterface} — the resolved, immutable snapshot
 *    (the primary entry point consumers depend on).
 *  - {@see ConfigurationBuilder} — the fully configured builder, for advanced
 *    callers that want to re-resolve or introspect.
 *
 * The heavy lifting lives in {@see ConfigureItSnapshotFactory}; this extension
 * is only responsible for translating the processed bundle config into service
 * definitions.
 */
final class ConfigureItExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{sources: list<string>, environment: ?string, schema: ?string, cache: ?string} $config */
        $config = $this->processConfiguration(new ConfigureItConfiguration(), $configs);

        $container
            ->register(ConfigurationSnapshotInterface::class)
            ->setFactory([ConfigureItSnapshotFactory::class, 'build'])
            ->addArgument($config)
            ->setPublic(true);

        $container
            ->register(ConfigurationBuilder::class)
            ->setFactory([ConfigureItSnapshotFactory::class, 'builder'])
            ->addArgument($config)
            ->setPublic(true);
    }
}
