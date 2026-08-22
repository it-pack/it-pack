<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\Wiring;

use ItPack\ConfigureIt\Bridge\OpenTelemetry\Contract\TelemetrySinkInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\DecrypterInterface;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;

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
}
