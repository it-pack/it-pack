<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;

interface ConfigurationFactoryInterface
{
    /**
     * @param iterable<ConfigurationLoaderInterface> $loaders
     * @param iterable<ConfigurationSourceInterface> $sources
     * @param list<ConfigurationProcessorInterface> $processors
     */
    public function build(
        iterable                     $loaders,
        ConfigurationReaderInterface $reader,
        ?ConfigurationSchemaInterface $schema = null,
        iterable                     $sources = [],
        array                        $processors = [],
        ?ResolutionReporterInterface $reporter = null,
        bool                         $failOnWarnings = false,
    ): ConfigurationResolverInterface;
}
