<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Adapter\CompositeReader;
use ItPack\ConfigureIt\Contract\ConfigurationFactoryInterface;
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
 * Readers are dispatched by format, mirroring how loaders are already
 * dispatched by {@see ConfigurationLoaderFactory}: each added reader declares
 * which resources it {@see ConfigurationReaderInterface::supports()}, and the
 * resolver picks the first match via {@see CompositeReader}.
 *
 * @see docs/architecture/MODEL.md "Configuration Builder"
 */
final class ConfigurationBuilder
{
    /**
     * @var list<ConfigurationReaderInterface>
     */
    private array $readers = [];

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
        private readonly ConfigurationFactoryInterface $factory = new ConfigurationFactory(),
    ) {
    }

    public function addReader(ConfigurationReaderInterface $reader): self
    {
        $this->readers[] = $reader;

        return $this;
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
        return $this->factory->build(
            loaders: $this->loaders,
            reader: new CompositeReader($this->readers),
            schema: $this->schema,
            sources: $this->sources,
            processors: $this->processors,
            reporter: $reporter,
        );
    }
}
