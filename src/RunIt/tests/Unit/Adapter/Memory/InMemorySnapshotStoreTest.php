<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Memory;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemorySnapshotStore;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemorySnapshotStore::class)]
final class InMemorySnapshotStoreTest extends Unit
{
    public function testStoreRetainsSnapshot(): void
    {
        $store = new InMemorySnapshotStore();
        $snapshot = $this->createMock(SnapshotInterface::class);

        $store->store($snapshot);

        self::assertSame([$snapshot], $store->stored());
    }
}
