<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Wiring;

use ItPack\ConfigureIt\Adapter\CompositeReader;
use ItPack\ConfigureIt\Adapter\Decryption\DecryptingReader;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\Contract\TelemetrySinkInterface;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\OpenTelemetryReporter;
use ItPack\ConfigureIt\Contract\ConfigurationCacheInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationResolverInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\DecrypterInterface;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Contract\Wiring\ConfigurationWiringInterface;
use ItPack\ConfigureIt\Core\CachingResolver;
use ItPack\ConfigureIt\Core\ConfigurationCompiler;
use ItPack\ConfigureIt\Core\ConfigurationGraphRewriter;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use ItPack\ConfigureIt\Execution\ResolutionReporter;

/**
 * Default composition root.
 *
 * The single place that instantiates Adapter/Bridge concretes on behalf of the
 * fluent {@see \ItPack\ConfigureIt\Core\ConfigurationBuilder}. Core depends on
 * {@see ConfigurationWiringInterface} only; this class supplies the real objects.
 */
final class DefaultConfigurationWiring implements ConfigurationWiringInterface
{
    /**
     * @param array<ConfigurationReaderInterface> $readers
     */
    public function composeReader(array $readers, ?DecrypterInterface $decrypter): ConfigurationReaderInterface
    {
        $reader = new CompositeReader($readers);

        if (null !== $decrypter) {
            $reader = new DecryptingReader($reader, $decrypter);
        }

        return $reader;
    }

    public function composeReporter(
        ResolutionReporterInterface $base,
        ?TelemetrySinkInterface $sink,
        ?ExecutionContextInterface $context,
    ): ResolutionReporterInterface {
        if (null === $sink) {
            return $base;
        }

        return new OpenTelemetryReporter($base, $sink, $context);
    }

    public function baseReporter(): ResolutionReporterInterface
    {
        return new ResolutionReporter();
    }

    public function baseExecutionContext(): ExecutionContextInterface
    {
        return new ExecutionContext();
    }

    public function graphRewriter(): ConfigurationGraphRewriter
    {
        return new ConfigurationGraphRewriter();
    }

    /**
     * @param list<ConfigurationSourceInterface>    $defaultSources
     * @param list<ConfigurationProcessorInterface> $processors
     * @param list<ConfigurationReaderInterface>    $readers
     * @param list<ConfigurationLoaderInterface>    $loaders
     */
    public function cachingResolver(
        ConfigurationResolverInterface $inner,
        ConfigurationCacheInterface $cache,
        array $defaultSources,
        ?ConfigurationSchemaInterface $schema,
        array $processors,
        array $readers,
        array $loaders,
        ?ExecutionContextInterface $executionContext,
        ?ResolutionReporterInterface $reporter = null,
    ): ConfigurationResolverInterface {
        return new CachingResolver(
            inner: $inner,
            cache: $cache,
            compiler: new ConfigurationCompiler(),
            defaultSources: $defaultSources,
            schema: $schema,
            processors: $processors,
            readers: $readers,
            loaders: $loaders,
            executionContext: $executionContext,
            reporter: $reporter,
        );
    }
}
