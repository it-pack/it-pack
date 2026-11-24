<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\ObservationIdInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;

/**
 * @template-implements StringIdentifierFactoryInterface<ObservationIdInterface>
 */
final class TestObservationIdFactory implements StringIdentifierFactoryInterface
{
    public function build(): ObservationIdInterface
    {
        return ObservationId::fromString('obs-1');
    }
}

/**
 * @template-implements StringIdentifierFactoryInterface<OccurrenceIdInterface>
 */
final class TestOccurrenceIdFactory implements StringIdentifierFactoryInterface
{
    public function build(): OccurrenceIdInterface
    {
        return OccurrenceId::fromString('occ-1');
    }
}

/**
 * @template-implements StringIdentifierFactoryInterface<SnapshotIdInterface>
 */
final class TestSnapshotIdFactory implements StringIdentifierFactoryInterface
{
    public function build(): SnapshotIdInterface
    {
        return SnapshotId::fromString('snap-1');
    }
}
