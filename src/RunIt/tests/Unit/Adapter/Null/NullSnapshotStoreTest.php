<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Null;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Null\NullSnapshotStore;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NullSnapshotStore::class)]
final class NullSnapshotStoreTest extends Unit
{
    public function testStoreIsNoop(): void
    {
        $store = new NullSnapshotStore();
        $snapshot = $this->createMock(SnapshotInterface::class);

        $store->store($snapshot);

        self::assertTrue(true);
    }
}
