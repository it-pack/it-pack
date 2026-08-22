<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSymfony;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Bundle configuration tree for `configure_it`.
 *
 * Mirrors the public {@see \ItPack\ConfigureIt\Core\ConfigurationBuilder} surface
 * that this bundle can drive: where to read configuration from (`sources`),
 * the active `environment`, an optional schema class, and an optional cache
 * directory.
 */
final class ConfigureItConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('configure_it');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->arrayNode('sources')
                    ->scalarPrototype()->end()
                    ->requiresAtLeastOneElement()
                    ->defaultValue([])
                ->end()
                ->scalarNode('environment')->defaultNull()->end()
                ->scalarNode('schema')->defaultNull()->end()
                ->scalarNode('cache')->defaultNull()->end()
            ->end();

        return $treeBuilder;
    }
}
