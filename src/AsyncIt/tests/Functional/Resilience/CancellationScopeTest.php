<?php

declare(strict_types=1);

namespace Tests\Functional\Resilience;

use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\AsyncIt\Domain\Exception\CancellationException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\CancellationScope
 */
final class CancellationScopeTest extends TestCase
{
    public function testCancellationScopeRecordsReason(): void
    {
        $scope = new CancellationScope();

        self::assertFalse($scope->isCancelled());
        self::assertNull($scope->reason());

        $scope->cancel(CancellationReason::DeadlineExceeded);

        self::assertTrue($scope->isCancelled());
        self::assertSame(CancellationReason::DeadlineExceeded, $scope->reason());
    }

    public function testCancellationReasonIsImmutableOnceSet(): void
    {
        $scope = new CancellationScope();

        $scope->cancel(CancellationReason::UserInitiated);
        $scope->cancel(CancellationReason::SystemShutdown);

        self::assertSame(CancellationReason::UserInitiated, $scope->reason());
    }

    public function testThrowIfCancelledThrowsWhenCancelled(): void
    {
        $scope = new CancellationScope();
        $scope->cancel(CancellationReason::ParentCancelled);

        $this->expectException(CancellationException::class);
        $scope->throwIfCancelled();
    }

    public function testCancellationExceptionCarriesReason(): void
    {
        $scope = new CancellationScope();
        $scope->cancel(CancellationReason::DeadlineExceeded);

        try {
            $scope->throwIfCancelled();
            self::fail('Expected CancellationException.');
        } catch (CancellationException $cancellationException) {
            self::assertSame(CancellationReason::DeadlineExceeded, $cancellationException->reason());
        }
    }

    public function testOnCancelCallbackFiresWhenScopeCancelled(): void
    {
        $scope = new CancellationScope();
        $fired = false;

        $scope->onCancel(static function () use (&$fired): void {
            $fired = true;
        });
        $scope->cancel(CancellationReason::UserInitiated);

        self::assertTrue($fired);
    }
}
