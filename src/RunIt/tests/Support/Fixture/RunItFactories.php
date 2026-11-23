<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use ItPack\RunIt\SDK\RunItInterface;

/**
 * Pre-wired RunIt runtimes for tests and quick start.
 *
 * These factories live in test support so they may use adapter defaults (the SDK layer ships no
 * concrete port implementations by design). Use {@see self::inMemory()} for tests that assert on
 * published artifacts and {@see self::nullRuntime()} when only execution bookkeeping matters.
 */
final class RunItFactories
{
    public static function nullRuntime(): RunItInterface
    {
        return self::configuredBuilder()
            ->build()
        ;
    }

    public static function inMemory(): RunItInterface
    {
        return self::configuredDefaultBuilder()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->build()
        ;
    }

    /**
     * Returns a fully configured, unbuilt builder matching the defaults used by
     * builder-setup tests. Collaborators and defaults match across tests, so the
     * setup is reused here instead of duplicated.
     */
    public static function configuredBuilder(): RunItBuilder
    {
        return self::configuredDefaultBuilder()
            ->withPublisher(new InMemoryArtifactPublisher())
        ;
    }

    public static function configuredDefaultBuilder(): RunItBuilder
    {
        return RunItBuilder::create()
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer());
    }
}
