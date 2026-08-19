<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Adapter\Memory\InMemorySnapshotStore;
use ItPack\RunIt\Adapter\Null\NullArtifactPublisher;
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
        return RunItBuilder::create()
            ->withPublisher(new NullArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->build()
        ;
    }

    public static function inMemory(): RunItInterface
    {
        return RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withSnapshotStore(new InMemorySnapshotStore())
            ->build()
        ;
    }
}
