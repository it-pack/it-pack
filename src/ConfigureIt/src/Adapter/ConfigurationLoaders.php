<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;

/**
 * Bundled {@see ConfigurationLoaderInterface} implementations.
 *
 * Centralises the built-in loaders so consumers can opt into the full set
 * without enumerating adapters. Lives in the adapter layer; the Core never
 * depends on it (ADR-0002 boundary).
 */
final class ConfigurationLoaders
{
    /**
     * @return list<ConfigurationLoaderInterface>
     */
    public static function default(): array
    {
        return [
            new PhpLoader(),
            new JsonLoader(),
            new YamlLoader(),
            new TomlLoader(),
            new IniLoader(),
            new XmlLoader(),
            new FrontMatterLoader(),
            new DotenvLoader(),
        ];
    }
}
