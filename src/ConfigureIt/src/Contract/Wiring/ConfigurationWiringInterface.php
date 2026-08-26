<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\Wiring;

use ItPack\ConfigureIt\Bridge\OpenTelemetry\Contract\TelemetrySinkInterface;
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
use ItPack\ConfigureIt\Core\ConfigurationGraphRewriter;

/**
 * Composition-root seam that knows how to assemble decorated readers/reporters.
 *
 * Per AGENTS.md, Core depends only on Contracts + ValueObjects and must never
 * reference concrete Adapters or Bridges. The actual wiring — wrapping the user's
 * readers in a {@see \ItPack\ConfigureIt\Adapter\CompositeReader}, optionally
 * decrypting via {@see \ItPack\ConfigureIt\Adapter\Decryption\DecryptingReader},
 * and optionally instrumenting via
 * {@see \ItPack\ConfigureIt\Bridge\OpenTelemetry\OpenTelemetryReporter} — lives
 * behind this contract. The concrete implementation is the composition root and
 * is the only place allowed to `new` those Adapter/Bridge classes.
 */
interface ConfigurationWiringInterface
{
    /**
     * Compose the reader chain from the builder's registered readers, optionally
     * wrapping it in a decrypting reader when a decrypter is configured.
     *
     * @param array<ConfigurationReaderInterface> $readers
     */
    public function composeReader(array $readers, ?DecrypterInterface $decrypter): ConfigurationReaderInterface;

    /**
     * Decorate the base reporter with OpenTelemetry instrumentation when a
     * telemetry sink is configured; otherwise return it unchanged.
     */
    public function composeReporter(
        ResolutionReporterInterface $base,
        ?TelemetrySinkInterface $sink,
        ?ExecutionContextInterface $context,
    ): ResolutionReporterInterface;

    /**
     * The default (base) resolution reporter, used when the builder supplies none.
     */
    public function baseReporter(): ResolutionReporterInterface;

    /**
     * The shared node-rewrite collaborator used by node-rewriting processors
     * (e.g. {@see \ItPack\ConfigureIt\Core\CastingProcessor},
     * {@see \ItPack\ConfigureIt\Core\ValidationProcessor}).
     *
     * Exposed as a composition-root service so processors receive it via
     * dependency injection rather than constructing it internally, keeping the
     * "no service is built inside another service" policy (NV10) intact.
     */
    public function graphRewriter(): ConfigurationGraphRewriter;

    /**
     * The default execution context, used when the builder supplies none and a
     * layer/context-aware processor needs one.
     */
    public function baseExecutionContext(): ExecutionContextInterface;

    /**
     * Wrap a resolver in the opt-in compiled-artifact caching decorator.
     *
     * Mirrors {@see self::composeReader()}/{@see self::composeReporter()}: the
     * builder stays a pure collector of configuration while the composition root
     * owns construction of the concrete {@see \ItPack\ConfigureIt\Core\CachingResolver}
     * (and its {@see \ItPack\ConfigureIt\Core\ConfigurationCompiler}).
     *
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
    ): ConfigurationResolverInterface;
}
