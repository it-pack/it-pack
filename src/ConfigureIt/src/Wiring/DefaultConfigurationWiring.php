<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Wiring;

use ItPack\ConfigureIt\Adapter\CompositeReader;
use ItPack\ConfigureIt\Adapter\Decryption\DecryptingReader;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\Contract\TelemetrySinkInterface;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\OpenTelemetryReporter;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\DecrypterInterface;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Contract\Wiring\ConfigurationWiringInterface;

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

        if ($decrypter !== null) {
            $reader = new DecryptingReader($reader, $decrypter);
        }

        return $reader;
    }

    public function composeReporter(
        ResolutionReporterInterface $base,
        ?TelemetrySinkInterface $sink,
        ?ExecutionContextInterface $context,
    ): ResolutionReporterInterface {
        if ($sink === null) {
            return $base;
        }

        return new OpenTelemetryReporter($base, $sink, $context);
    }
}
