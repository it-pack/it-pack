<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Contract\ConfigurationCacheInterface;
use ItPack\ConfigureIt\Contract\ConfigurationResolverInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Core\CachingResolver;
use ItPack\ConfigureIt\Core\ConfigurationCompiler;
use ItPack\ConfigureIt\Core\ConfigurationSnapshot;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use RuntimeException;

/**
 * R12 — a best-effort cache write failure must still yield the live snapshot and
 * (when a reporter is wired in) record a warning observation.
 *
 * @covers \ItPack\ConfigureIt\Core\CachingResolver
 */
final class CachingResolverTest extends Unit
{
    public function testThrowingCacheStillReturnsLiveSnapshot(): void
    {
        $inner = $this->innerReturning(new ConfigurationSnapshot([]));
        $cache = $this->throwingCache();

        $resolver = new CachingResolver(
            inner: $inner,
            cache: $cache,
            compiler: new ConfigurationCompiler(),
            defaultSources: [],
            schema: null,
            processors: [],
            readers: [],
            loaders: [],
            executionContext: null,
        );

        verify($resolver->resolve([]))->same($inner->resolve([]));
    }

    public function testThrowingCacheRecordsWarningObservationWhenReporterGiven(): void
    {
        $inner = $this->innerReturning(new ConfigurationSnapshot([]));
        $cache = $this->throwingCache();
        $reporter = new ResolutionReporter();

        $resolver = new CachingResolver(
            inner: $inner,
            cache: $cache,
            compiler: new ConfigurationCompiler(),
            defaultSources: [],
            schema: null,
            processors: [],
            readers: [],
            loaders: [],
            executionContext: null,
            reporter: $reporter,
        );

        $resolver->resolve([]);

        $observations = $reporter->observations();

        verify(count($observations))->equals(1);
        verify($observations[0]->level())->equals('warning');
        verify($observations[0]->message())->equals('Cache write failed: disk full');
    }

    public function testThrowingCacheRecordsNoObservationWhenNoReporter(): void
    {
        $inner = $this->innerReturning(new ConfigurationSnapshot([]));
        $cache = $this->throwingCache();

        $resolver = new CachingResolver(
            inner: $inner,
            cache: $cache,
            compiler: new ConfigurationCompiler(),
            defaultSources: [],
            schema: null,
            processors: [],
            readers: [],
            loaders: [],
            executionContext: null,
        );

        $snapshot = $resolver->resolve([]);

        verify($snapshot)->instanceOf(ConfigurationSnapshotInterface::class);
    }
    private function innerReturning(ConfigurationSnapshotInterface $snapshot): ConfigurationResolverInterface
    {
        return new class ($snapshot) implements ConfigurationResolverInterface {
            public function __construct(private ConfigurationSnapshotInterface $snapshot)
            {
            }

            public function resolve(iterable $sources = []): ConfigurationSnapshotInterface
            {
                return $this->snapshot;
            }
        };
    }

    private function throwingCache(): ConfigurationCacheInterface
    {
        return new class () implements ConfigurationCacheInterface {
            public function get(string $key): ?ConfigurationSnapshotInterface
            {
                return null;
            }

            public function set(string $key, ConfigurationSnapshotInterface $snapshot): void
            {
                throw new RuntimeException('disk full');
            }
        };
    }
}
