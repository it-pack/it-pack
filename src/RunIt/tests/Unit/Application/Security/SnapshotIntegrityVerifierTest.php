<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Security;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Security\SnapshotIntegrityException;
use ItPack\RunIt\Application\Security\SnapshotIntegrityVerifier;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;

/**
 * @group application
 * @group security
 */
final class SnapshotIntegrityVerifierTest extends Unit
{
    private function snapshot(
        string $id,
        mixed $payload,
        HashCalculatorInterface $calculator,
        ?string $predecessorId = null,
        ?int $createdAt = null
    ): Snapshot {
        return Snapshot::create(
            SnapshotId::fromString($id),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            $payload,
            $calculator,
            predecessorId: $predecessorId === null ? null : SnapshotId::fromString($predecessorId),
            createdAt: $createdAt === null ? null : Instant::of($createdAt),
        );
    }

    public function testValidSnapshotPassesIntegrity(): void
    {
        $snapshot = $this->snapshot('snap-1', ['status' => 'ok'], new DeterministicHashCalculator());

        new SnapshotIntegrityVerifier(new DeterministicHashCalculator())->verifyIntegrity($snapshot);
    }

    public function testTamperedHashIsDetected(): void
    {
        // Calculator that produces a content hash unrelated to the payload.
        $fixed = new class () implements HashCalculatorInterface {
            public function calculate(string $content): string
            {
                return 'not-the-real-hash';
            }
        };

        $snapshot = $this->snapshot('snap-1', ['status' => 'ok'], $fixed);

        $this->expectException(SnapshotIntegrityException::class);
        $this->expectExceptionMessageMatches('/tamper/i');

        new SnapshotIntegrityVerifier(new DeterministicHashCalculator())->verifyIntegrity($snapshot);
    }

    public function testConsistentPredecessorChainPasses(): void
    {
        $calculator = new DeterministicHashCalculator();
        $first = $this->snapshot('snap-1', ['step' => 1], $calculator);
        $second = $this->snapshot('snap-2', ['step' => 2], $calculator, 'snap-1');

        $verifier = new SnapshotIntegrityVerifier($calculator);
        $verifier->verifyIntegrity($second);
        $verifier->verifyChain($second, $first);
    }

    public function testMissingPredecessorIsRejected(): void
    {
        $second = $this->snapshot('snap-2', ['step' => 2], new DeterministicHashCalculator(), 'snap-1');

        $this->expectException(SnapshotIntegrityException::class);
        $this->expectExceptionMessageMatches('/missing predecessor/i');

        new SnapshotIntegrityVerifier(new DeterministicHashCalculator())->verifyChain($second, predecessor: null);
    }

    public function testPredecessorMismatchIsRejected(): void
    {
        $calculator = new DeterministicHashCalculator();
        $second = $this->snapshot('snap-2', ['step' => 2], $calculator, 'snap-1');
        $wrongPredecessor = $this->snapshot('snap-x', ['step' => 0], $calculator);

        $this->expectException(SnapshotIntegrityException::class);
        $this->expectExceptionMessageMatches('/predecessor mismatch/i');

        new SnapshotIntegrityVerifier($calculator)->verifyChain($second, $wrongPredecessor);
    }

    public function testPredecessorAfterSnapshotIsRejected(): void
    {
        $calculator = new DeterministicHashCalculator();
        $this->snapshot('snap-1', ['step' => 1], $calculator, createdAt: 1000);
        $second = $this->snapshot('snap-2', ['step' => 2], $calculator, 'snap-1', 1000);
        $firstLater = $this->snapshot('snap-1', ['step' => 1], $calculator, createdAt: 2000);

        $this->expectException(SnapshotIntegrityException::class);
        $this->expectExceptionMessageMatches('/older than its predecessor/i');

        new SnapshotIntegrityVerifier($calculator)->verifyChain($second, $firstLater);
    }

    public function testChainWithUnexpectedPredecessorIsRejected(): void
    {
        $snapshot = $this->snapshot('snap-root', ['status' => 'ok'], new DeterministicHashCalculator());
        $predecessor = $this->snapshot('snap-older', ['status' => 'ok'], new DeterministicHashCalculator());

        $this->expectException(SnapshotIntegrityException::class);
        $this->expectExceptionMessageMatches('/predecessor/i');

        new SnapshotIntegrityVerifier(new DeterministicHashCalculator())->verifyChain($snapshot, $predecessor);
    }

    public function testRootSnapshotWithoutPredecessorPassesTheChainCheck(): void
    {
        $snapshot = $this->snapshot('snap-root', ['status' => 'ok'], new DeterministicHashCalculator());

        new SnapshotIntegrityVerifier(new DeterministicHashCalculator())->verifyChain($snapshot, predecessor: null);

        self::expectNotToPerformAssertions();
    }
}
