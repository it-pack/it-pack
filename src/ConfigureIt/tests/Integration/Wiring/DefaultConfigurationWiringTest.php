<?php

declare(strict_types=1);

namespace Tests\Integration\Wiring;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\CompositeReader;
use ItPack\ConfigureIt\Adapter\Decryption\DecryptingReader;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\InMemoryTelemetrySink;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\OpenTelemetryReporter;
use ItPack\ConfigureIt\Contract\ConfigurationCacheInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationResolverInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Core\CachingResolver;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use ItPack\ConfigureIt\Wiring\DefaultConfigurationWiring;
use LogicException;

/**
 * F1 — the composition root is the only place that assembles Adapter/Bridge
 * concretes on the builder's behalf.
 *
 * @covers \ItPack\ConfigureIt\Wiring\DefaultConfigurationWiring
 */
final class DefaultConfigurationWiringTest extends Unit
{
    private DefaultConfigurationWiring $wiring;

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

    public function testCachingResolverWrapsInnerResolverInCachingResolver(): void
    {
        $inner = new class () implements ConfigurationResolverInterface {
            public function resolve(iterable $sources = []): ConfigurationSnapshotInterface
            {
                throw new LogicException('not used');
            }
        };

        $cache = new class () implements ConfigurationCacheInterface {
            public function get(string $key): ?ConfigurationSnapshotInterface
            {
                return null;
            }

            public function set(string $key, ConfigurationSnapshotInterface $snapshot): void
            {
            }
        };

        $resolver = $this->wiring->cachingResolver($inner, $cache, [], null, [], [], [], null);

        verify($resolver)->instanceOf(ConfigurationResolverInterface::class);
        verify($resolver)->instanceOf(CachingResolver::class);
    }

    protected function _before(): void
    {
        $this->wiring = new DefaultConfigurationWiring();
    }
}
