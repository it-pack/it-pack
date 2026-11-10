<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\SnapshotStoreVerifier;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use RuntimeException;

/**
 * @group conformance
 */
final class SnapshotStoreVerifierTest extends Unit
{
    public function testPortReportsSnapshotStoreInterface(): void
    {
        self::assertSame(SnapshotStoreInterface::class, new SnapshotStoreVerifier()->port());
    }

    public function testConformantStorePasses(): void
    {
        $store = new class () implements SnapshotStoreInterface {
            public function store(SnapshotInterface $snapshot): void
            {
            }
        };

        $result = new SnapshotStoreVerifier()->verify($store, new RunItPortFixture());

        self::assertTrue($result->isConformant());
        self::assertCount(0, $result);
    }

    public function testStoreThrowIsRecorded(): void
    {
        $store = new class () implements SnapshotStoreInterface {
            public function store(SnapshotInterface $snapshot): void
            {
                throw new RuntimeException('store broken');
            }
        };

        $result = new SnapshotStoreVerifier()->verify($store, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertCount(1, $result->errors());
        self::assertSame('store.throws', $result->errors()[0]->id);
    }

    public function testPatchedStoreThrowIsRecorded(): void
    {
        $store = new class () implements SnapshotStoreInterface {
            private int $stores = 0;

            public function store(SnapshotInterface $snapshot): void
            {
                $this->stores++;

                if ($this->stores === 3) {
                    throw new RuntimeException('patched store broken');
                }
            }
        };

        $result = new SnapshotStoreVerifier()->verify($store, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertSame('store.patched.throws', $result->errors()[0]->id);
    }
}
