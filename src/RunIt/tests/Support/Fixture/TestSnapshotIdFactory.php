<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;

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
