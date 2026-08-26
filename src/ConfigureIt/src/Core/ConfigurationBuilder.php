<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Bridge\OpenTelemetry\Contract\TelemetrySinkInterface;
use ItPack\ConfigureIt\Contract\ConfigurationCacheInterface;
use ItPack\ConfigureIt\Contract\ConfigurationFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLayerInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationResolverInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\DecrypterInterface;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Contract\ExecutionContextAwareProcessorInterface;
use ItPack\ConfigureIt\Contract\Wiring\ConfigurationWiringInterface;
use ItPack\ConfigureIt\Wiring\DefaultConfigurationWiring;

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

    private ?ExecutionContextInterface $executionContext = null;

    private ?ConfigurationCacheInterface $cache = null;

    private ?DecrypterInterface $decrypter = null;

    private bool $failOnWarnings = false;

    public function __construct(
        private readonly ConfigurationFactoryInterface $factory = new ConfigurationFactory(),
        private readonly ConfigurationWiringInterface $wiring = new DefaultConfigurationWiring(),
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

    public function withExecutionContext(ExecutionContextInterface $context): self
    {
        $this->executionContext = $context;

        return $this;
    }

    /**
     * Opt in to compiled-artifact caching for this builder.
     *
     * When a cache is set, the resolver produced by {@see self::build()} serves
     * an immutable snapshot from the cache whenever the composite resolution
     * hash is unchanged, and re-resolves + rewrites the artifact on any change
     * (source edit, schema change, context change). The behaviour is otherwise
     * identical; callers see the same snapshot and the same failure modes.
     */
    public function withCache(ConfigurationCacheInterface $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Opt in to transparent decryption of `enc:`/`sops:`-annotated resources.
     *
     * When a decrypter is set, the reader chain is wrapped in a
     * {@see DecryptingReader} so any resource whose raw payload carries the
     * annotation is decrypted before the loader parses it. Resources without the
     * marker pass through untouched, so this is safe to enable globally.
     */
    public function withDecrypter(DecrypterInterface $decrypter): self
    {
        $this->decrypter = $decrypter;

        return $this;
    }

    /**
     * Treat schema warnings (e.g. deprecations) as fatal.
     *
     * By default only errors block resolution and warnings are surfaced as
     * `Observation`s. Enabling this makes a warning-only result throw a
     * {@see \ItPack\ConfigureIt\Exception\ValidationException} instead.
     */
    public function failOnWarnings(bool $failOnWarnings = true): self
    {
        $this->failOnWarnings = $failOnWarnings;

        return $this;
    }

    public function build(?ResolutionReporterInterface $reporter = null): ConfigurationResolverInterface
    {
        $reporter ??= $this->wiring->baseReporter();

        $reporter = $this->wiring->composeReporter(
            $reporter,
            $this->telemetrySink,
            $this->executionContext,
        );

        $processors = $this->processors;

        if (null !== $this->executionContext) {
            $context = $this->executionContext;

            $processors = array_map(
                static fn (ConfigurationProcessorInterface $processor): ConfigurationProcessorInterface
                    => $processor instanceof ExecutionContextAwareProcessorInterface
                        ? $processor->withExecutionContext($context)
                        : $processor,
                $processors,
            );
        }

        $sources = $this->resolveSources();

        $reader = $this->wiring->composeReader($this->readers, $this->decrypter);

        $resolver = $this->factory->build(
            loaders: $this->loaders,
            reader: $reader,
            schema: $this->schema,
            sources: $sources,
            processors: $processors,
            reporter: $reporter,
            failOnWarnings: $this->failOnWarnings,
        );

        if ($this->cache instanceof ConfigurationCacheInterface) {
            return $this->wiring->cachingResolver(
                inner: $resolver,
                cache: $this->cache,
                defaultSources: $sources,
                schema: $this->schema,
                processors: $processors,
                readers: $this->readers,
                loaders: $this->loaders,
                executionContext: $this->executionContext,
                reporter: $reporter,
            );
        }

        return $resolver;
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
        if ([] === $this->layers) {
            return $this->sources;
        }

        $context = $this->executionContext ?? $this->wiring->baseExecutionContext();

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
            $layerId = $layer->id();

            foreach ($layer->sources() as $source) {
                $sources[] = new LayeredSource($source, $layerId);
            }
        }

        return $sources;
    }
}
