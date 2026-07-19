<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationResolverInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Execution\ResolutionReporter;

/**
 * Mutable fluent API for assembling a configuration resolution pipeline.
 *
 * Builders are mutable; the produced snapshot is not. Mirrors the
 * `ConfigureIt::builder()` vision in docs/VISION.md.
 *
 * @see docs/architecture/MODEL.md "Configuration Builder"
 */
final class ConfigurationBuilder
{
    /**
     * @var list<ConfigurationSourceInterface>
     */
    private array $sources = [];

    /**
     * @var list<ConfigurationLoaderInterface>
     */
    private array $loaders = [];

    /**
     * @var list<ConfigurationProcessorInterface>
     */
    private array $processors = [];

    private ?ConfigurationSchemaInterface $schema = null;

    public function __construct(
        private readonly ConfigurationReaderInterface $reader,
    ) {
    }

    public function addSource(ConfigurationSourceInterface $source): self
    {
        $this->sources[] = $source;

        return $this;
    }

    public function addLoader(ConfigurationLoaderInterface $loader): self
    {
        $this->loaders[] = $loader;

        return $this;
    }

    public function addProcessor(ConfigurationProcessorInterface $processor): self
    {
        $this->processors[] = $processor;

        return $this;
    }

    public function withSchema(ConfigurationSchemaInterface $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    public function build(?ResolutionReporter $reporter = null): ConfigurationResolverInterface
    {
        $pipeline = new ConfigurationPipeline($this->processors, $reporter);

        return new ConfigurationResolver(
            reader: $this->reader,
            loaders: $this->loaders,
            pipeline: $pipeline,
            schema: $this->schema,
            reporter: $reporter,
            sources: $this->sources,
        );
    }
}
