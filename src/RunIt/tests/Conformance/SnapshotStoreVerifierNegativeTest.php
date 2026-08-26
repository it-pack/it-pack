<?php

declare(strict_types=1);

namespace Tests\Conformance;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemorySnapshotStore;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\SnapshotStoreVerifier;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use RuntimeException;
use Tests\Support\Fixture\Conformance\ConformanceViolations;
use Tests\Support\Fixture\Conformance\ImpureNormalizeSnapshot;
use Tests\Support\Fixture\Conformance\OverridingPortFixture;

/**
 * Feeds the {@see SnapshotStoreVerifier} broken stores and impure fixture
 * material and asserts each contract breach is reported as expected.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class SnapshotStoreVerifierNegativeTest extends Unit
{
    private RunItPortFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new RunItPortFixture();
    }

    public function testThrowingStoreIsReportedAndStopsTheCheck(): void
    {
        $store = new class () implements SnapshotStoreInterface {
            public function store(SnapshotInterface $snapshot): void
            {
                throw new RuntimeException('store unavailable');
            }
        };

        $result = new SnapshotStoreVerifier()->verify($store, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['store.throws'], ConformanceViolations::ids($result));
        self::assertSame(1, count($result));
    }

    public function testRejectingARestoredSnapshotIsReported(): void
    {
        $store = new class () implements SnapshotStoreInterface {
            private bool $primed = false;

            private mixed $seenPayload = null;

            public function store(SnapshotInterface $snapshot): void
            {
                if (!$this->primed) {
                    $this->primed = true;
                    $this->seenPayload = $snapshot->payload();

                    return;
                }

                if ($snapshot->payload() !== $this->seenPayload) {
                    throw new RuntimeException('payload conflict on re-store');
                }
            }
        };

        $result = new SnapshotStoreVerifier()->verify($store, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['store.patched.throws'], ConformanceViolations::ids($result));
    }

    public function testMutatedSnapshotIsReported(): void
    {
        $fixture = new OverridingPortFixture(
            $this->fixture,
            snapshot: new ImpureNormalizeSnapshot($this->fixture->snapshot()),
        );

        $result = new SnapshotStoreVerifier()->verify(new InMemorySnapshotStore(), $fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['store.mutates-snapshot'], ConformanceViolations::ids($result));
    }
}
