<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Security;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Security\SnapshotIntegrityException;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(SnapshotIntegrityException::class)]
final class SnapshotIntegrityExceptionTest extends Unit
{
    public function testExtendsRuntimeException(): void
    {
        $e = SnapshotIntegrityException::tampered('snap-1');

        self::assertInstanceOf(RuntimeException::class, $e);
        self::assertInstanceOf(RunItExceptionInterface::class, $e);
    }

    public function testTamperedMessage(): void
    {
        $e = SnapshotIntegrityException::tampered('snap-1');

        self::assertSame(
            'Snapshot "snap-1" content hash does not match its payload (tamper detected).',
            $e->getMessage(),
        );
    }

    public function testMissingPredecessorMessage(): void
    {
        $e = SnapshotIntegrityException::missingPredecessor('snap-1', 'pred-1');

        self::assertSame(
            'Snapshot "snap-1" references missing predecessor "pred-1".',
            $e->getMessage(),
        );
    }

    public function testUnexpectedPredecessorMessage(): void
    {
        $e = SnapshotIntegrityException::unexpectedPredecessor('snap-1');

        self::assertSame(
            'Snapshot "snap-1" has no predecessor reference but one was supplied.',
            $e->getMessage(),
        );
    }

    public function testPredecessorMismatchMessage(): void
    {
        $e = SnapshotIntegrityException::predecessorMismatch('snap-1', 'expected-1', 'actual-1');

        self::assertSame(
            'Snapshot "snap-1" predecessor mismatch: expected "expected-1", got "actual-1".',
            $e->getMessage(),
        );
    }

    public function testPredecessorOrderMessage(): void
    {
        $e = SnapshotIntegrityException::predecessorOrder('snap-1', 'pred-1');

        self::assertSame(
            'Snapshot "snap-1" is older than its predecessor "pred-1".',
            $e->getMessage(),
        );
    }
}
