<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\AsyncIt\Domain\Exception\CancellationException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\CancellationScope
 */
final class CancellationScopeTest extends TestCase
{
    public function testNotCancelledInitially(): void
    {
        $scope = new CancellationScope();

        self::assertFalse($scope->isCancelled());
    }

    public function testThrowIfCancelledDoesNotThrowWhenAlive(): void
    {
        $scope = new CancellationScope();

        $scope->throwIfCancelled();

        self::assertFalse($scope->isCancelled());
    }

    public function testCancelSetsFlag(): void
    {
        $scope = new CancellationScope();

        $scope->cancel();

        self::assertTrue($scope->isCancelled());
    }

    public function testThrowIfCancelledThrowsAfterCancel(): void
    {
        $scope = new CancellationScope();
        $scope->cancel();

        $this->expectException(CancellationException::class);
        $scope->throwIfCancelled();
    }

    public function testOnCancelRegistersCallbackForLaterCancellation(): void
    {
        $scope = new CancellationScope();
        $called = false;
        $scope->onCancel(static function () use (&$called): void {
            $called = true;
        });

        $scope->cancel();

        self::assertTrue($called);
    }

    public function testOnCancelRunsCallbackImmediatelyIfAlreadyCancelled(): void
    {
        $scope = new CancellationScope();
        $scope->cancel();
        $called = false;
        $scope->onCancel(static function () use (&$called): void {
            $called = true;
        });

        self::assertTrue($called);
    }

    public function testCancelIsIdempotent(): void
    {
        $scope = new CancellationScope();
        $scope->cancel();
        $scope->cancel();

        self::assertTrue($scope->isCancelled());
    }

    public function testMultipleCallbacksRunOnCancel(): void
    {
        $scope = new CancellationScope();
        $calls = [];
        $scope->onCancel(static function () use (&$calls): void {
            $calls[] = 'first';
        });
        $scope->onCancel(static function () use (&$calls): void {
            $calls[] = 'second';
        });

        $scope->cancel();

        self::assertSame(['first', 'second'], $calls);
    }
}
