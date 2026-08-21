<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Adapter\CompositeReader;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\Contract\TelemetrySinkInterface;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\OpenTelemetryReporter;
use ItPack\ConfigureIt\Contract\ConfigurationFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLayerInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationResolverInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Execution\ExecutionContext;
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
     * @var list<ConfigurationLayerInterface>
     */
    private array $layers = [];

    /**
     * @var list<ConfigurationLoaderInterface>
     */
    private array $loaders = [];

    /**
     * @var list<ConfigurationProcessorInterface>
     */
    private array $processors = [];

    private ?ConfigurationSchemaInterface $schema = null;

    private ?TelemetrySinkInterface $telemetrySink = null;

    private ?ExecutionContext $executionContext = null;

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

    /**
     * Register an explicit composition layer.
     *
     * When at least one layer is registered, the flat sources added via
     * {@see self::addSource()} are ignored and the resolved source set is derived
     * from the layers: each layer is kept only if it {@see ConfigurationLayerInterface::match()es}
     * the execution context, then the surviving layers are merged in ascending
     * {@see ConfigurationLayerInterface::precedence()} order (so higher precedence
     * overrides lower). With no layers, behaviour is identical to before.
     */
    public function addLayer(ConfigurationLayerInterface $layer): self
    {
        $this->layers[] = $layer;

        return $this;
    }

    public function addLoader(ConfigurationLoaderInterface $loader): self
    {
        $this->loaders[] = $loader;

        return $this;
    }

    /**
     * @param iterable<ConfigurationLoaderInterface> $loaders
     */
    public function addLoaders(iterable $loaders): self
    {
        foreach ($loaders as $loader) {
            $this->loaders[] = $loader;
        }

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

    public function withTelemetry(TelemetrySinkInterface $sink): self
    {
        $this->telemetrySink = $sink;

        return $this;
    }

    public function withExecutionContext(ExecutionContext $context): self
    {
        $this->executionContext = $context;

        return $this;
    }

    public function build(?ResolutionReporter $reporter = null): ConfigurationResolverInterface
    {
        $reporter ??= new ResolutionReporter();

        if ($this->telemetrySink instanceof TelemetrySinkInterface) {
            $reporter = new OpenTelemetryReporter(
                $reporter,
                $this->telemetrySink,
                $this->executionContext,
            );
        }

        return $this->factory->build(
            loaders: $this->loaders,
            reader: new CompositeReader($this->readers),
            schema: $this->schema,
            sources: $this->resolveSources(),
            processors: $this->processors,
            reporter: $reporter,
        );
    }

    /**
     * Derive the ordered source list from registered layers.
     *
     * Layers are filtered by their match predicate (against the execution
     * context), then sorted ascending by precedence so that higher precedence
     * layers — merged later — override lower ones. With no layers the flat
     * {@see self::$sources} list is returned unchanged (backward compatible).
     *
     * @return list<ConfigurationSourceInterface>
     */
    private function resolveSources(): array
    {
        if ($this->layers === []) {
            return $this->sources;
        }

        $context = $this->executionContext ?? new ExecutionContext();

        $active = array_filter(
            $this->layers,
            static fn (ConfigurationLayerInterface $layer): bool => $layer->match($context),
        );

        usort(
            $active,
            static fn (ConfigurationLayerInterface $a, ConfigurationLayerInterface $b): int
                => $a->precedence() <=> $b->precedence(),
        );

        $sources = [];

        foreach ($active as $layer) {
            foreach ($layer->sources() as $source) {
                $sources[] = $source;
            }
        }

        return $sources;
    }
}
