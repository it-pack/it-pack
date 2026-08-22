<?php

declare(strict_types=1);

namespace Tests\Unit\Wiring;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\CompositeReader;
use ItPack\ConfigureIt\Adapter\Decryption\DecryptingReader;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\InMemoryTelemetrySink;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\OpenTelemetryReporter;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use ItPack\ConfigureIt\Wiring\DefaultConfigurationWiring;

/**
 * F1 — the composition root is the only place that assembles Adapter/Bridge
 * concretes on the builder's behalf.
 *
 * @covers \ItPack\ConfigureIt\Wiring\DefaultConfigurationWiring
 */
final class DefaultConfigurationWiringTest extends Unit
{
    private DefaultConfigurationWiring $wiring;

    protected function _before(): void
    {
        $this->wiring = new DefaultConfigurationWiring();
    }

    public function testComposeReaderWrapsInCompositeReader(): void
    {
        $reader = $this->wiring->composeReader([], null);

        verify($reader)->instanceOf(ConfigurationReaderInterface::class);
        verify($reader)->instanceOf(CompositeReader::class);
    }

    public function testComposeReaderWrapsInDecryptingReaderWhenDecrypterSet(): void
    {
        $decrypter = new class () implements \ItPack\ConfigureIt\Contract\DecrypterInterface {
            public function decrypt(string $ciphertext): string
            {
                return $ciphertext;
            }
        };

        $reader = $this->wiring->composeReader([], $decrypter);

        verify($reader)->instanceOf(DecryptingReader::class);
    }

    public function testComposeReporterReturnsBaseWhenNoSink(): void
    {
        $base = new ResolutionReporter();

        $reporter = $this->wiring->composeReporter($base, null, null);

        verify($reporter)->same($base);
    }

    public function testComposeReporterDecoratesWithOpenTelemetryWhenSinkSet(): void
    {
        $reporter = $this->wiring->composeReporter(
            new ResolutionReporter(),
            new InMemoryTelemetrySink(),
            null,
        );

        verify($reporter)->instanceOf(ResolutionReporterInterface::class);
        verify($reporter)->instanceOf(OpenTelemetryReporter::class);
    }
}
