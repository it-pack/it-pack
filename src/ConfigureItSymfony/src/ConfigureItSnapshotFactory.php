<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSymfony;

use ItPack\ConfigureIt\Adapter\ConfigurationLoaders;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Cache\FileSystemCache;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use ItPack\ConfigureIt\Wiring\Configurations;

/**
 * Builds ConfigureIt objects from the processed bundle configuration.
 *
 * Kept separate from the DI {@see ConfigureItExtension} so the wiring is a pure,
 * testable data transformation: the extension registers these as Symfony
 * service factories, and consumers can reuse them directly.
 *
 * @internal
 */
final class ConfigureItSnapshotFactory
{
    /**
     * @param array{sources: list<string>, environment: ?string, schema: ?string, cache: ?string} $config
     */
    public static function build(array $config): \ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface
    {
        return self::builder($config)->build()->resolve();
    }

    /**
     * @param array{sources: list<string>, environment: ?string, schema: ?string, cache: ?string} $config
     */
    public static function builder(array $config): ConfigurationBuilder
    {
        $builder = Configurations::builder();
        $builder->addReader(new FileReader());

        foreach ($config['sources'] as $source) {
            $builder->addSource(new FileSource($source));
        }

        $builder->addLoaders(ConfigurationLoaders::default());

        if ($config['environment'] !== null) {
            $builder->withExecutionContext(new ExecutionContext($config['environment']));
        }

        if ($config['schema'] !== null) {
            /** @var class-string<ConfigurationSchemaInterface> $schemaClass */
            $schemaClass = $config['schema'];
            $builder->withSchema(new $schemaClass());
        }

        if ($config['cache'] !== null) {
            $builder->withCache(new FileSystemCache($config['cache']));
        }

        return $builder;
    }
}
