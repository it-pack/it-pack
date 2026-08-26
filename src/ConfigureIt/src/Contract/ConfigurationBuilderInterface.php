<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Bridge\OpenTelemetry\Contract\TelemetrySinkInterface;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;

/**
 * Fluent composition-root façade for building a resolver.
 *
 * Extracted from {@see \ItPack\ConfigureIt\Core\ConfigurationBuilder} so callers
 * (and tests) can depend on the contract; the concrete builder stays the only
 * place that knows the wiring (ADR-0002 / ADR-0008).
 */
interface ConfigurationBuilderInterface
{
    public function addReader(ConfigurationReaderInterface $reader): ConfigurationBuilderInterface;

    public function addSource(ConfigurationSourceInterface $source): ConfigurationBuilderInterface;

    public function addLayer(ConfigurationLayerInterface $layer): ConfigurationBuilderInterface;

    public function addLoader(ConfigurationLoaderInterface $loader): ConfigurationBuilderInterface;

    /**
     * @param iterable<ConfigurationLoaderInterface> $loaders
     */
    public function addLoaders(iterable $loaders): ConfigurationBuilderInterface;

    public function addProcessor(ConfigurationProcessorInterface $processor): ConfigurationBuilderInterface;

    public function withSchema(ConfigurationSchemaInterface $schema): ConfigurationBuilderInterface;

    public function withTelemetry(TelemetrySinkInterface $sink): ConfigurationBuilderInterface;

    public function withExecutionContext(ExecutionContextInterface $context): ConfigurationBuilderInterface;

    public function withCache(ConfigurationCacheInterface $cache): ConfigurationBuilderInterface;

    public function withDecrypter(DecrypterInterface $decrypter): ConfigurationBuilderInterface;

    public function failOnWarnings(bool $failOnWarnings = true): ConfigurationBuilderInterface;

    public function build(?ResolutionReporterInterface $reporter = null): ConfigurationResolverInterface;
}
