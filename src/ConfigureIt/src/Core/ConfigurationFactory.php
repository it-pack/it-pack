<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationResolverInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderFactoryInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Contract\SchemaInterface;
use ItPack\ConfigureIt\Execution\ResolutionReporter;

final readonly class ConfigurationFactory implements ConfigurationFactoryInterface
{

    public function __construct(
        private ?ResolutionReporterInterface $reporter = new ResolutionReporter(),
    ) {
    }

    /**
     * @param iterable<\ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface> $loaders
     * @param iterable<\ItPack\ConfigureIt\Contract\ConfigurationSourceInterface> $sources
     * @param list<\ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface> $processors
     */
    public function build(
        iterable                     $loaders,
        ConfigurationReaderInterface $reader,
        ?ConfigurationSchemaInterface $schema = null,
        iterable                     $sources = [],
        array                        $processors = [],
        ?ResolutionReporterInterface $reporter = null
    ): ConfigurationResolverInterface
    {
        return new ConfigurationResolver(
            reader: $reader,
            loaders:  new ConfigurationLoaderFactory($loaders),
            pipeline: new ConfigurationPipeline($processors, $reporter ?? $this->reporter),
            schema: $schema,
            reporter: $reporter ?? $this->reporter,
            sources: $sources,
        );
    }
}