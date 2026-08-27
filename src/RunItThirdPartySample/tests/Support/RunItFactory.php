<?php

declare(strict_types=1);

namespace ItPack\RunItThirdPartySample\Tests\Support;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use ItPack\RunIt\SDK\RunItInterface;

/**
 * Builds a fully-wired RunIt runtime using only the public SDK builder and the
 * shipped common adapters, so the sample tests never depend on RunIt's internal
 * test tooling.
 */
final class RunItFactory
{
    public static function create(
        ArtifactPublisherInterface $publisher,
        ?ContextStorageInterface $storage = null,
    ): RunItInterface {
        $builder = RunItBuilder::create()
            ->withPublisher($publisher)
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator());

        if ($storage !== null) {
            $builder = $builder->withContextStorage($storage);
        }

        return $builder->build();
    }
}
