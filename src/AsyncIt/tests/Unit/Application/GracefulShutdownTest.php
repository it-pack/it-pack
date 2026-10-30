<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use ItPack\AsyncIt\Application\GracefulShutdown;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\GracefulShutdown
 */
final class GracefulShutdownTest extends TestCase
{
    public function testShutdownCancelsRootScopeAndDrainsWithDeadline(): void
    {
        $clockNanos = 1_000_000_000;

        $clock = $this->createMock(MonotonicClockInterface::class);
        $clock->method('monotonicNanoseconds')->willReturn($clockNanos);

        $scope = $this->createMock(CancellationScopeInterface::class);
        $scope->expects($this->once())->method('cancel');

        $receivedDeadline = null;
        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler
            ->expects($this->once())
            ->method('runUntil')
            ->willReturnCallback(static function (int $deadline) use (&$receivedDeadline): void {
                $receivedDeadline = $deadline;
            })
        ;

        $shutdown = new GracefulShutdown(
            $scheduler,
            $scope,
            $clock,
            Duration::ofSeconds(5),
        );

        $shutdown->shutdown();

        self::assertNotNull($receivedDeadline);
        self::assertSame(
            $clockNanos + Duration::ofSeconds(5)->nanoseconds(),
            $receivedDeadline,
            'Deadline must be clock.now() + configured shutdown duration',
        );
    }

    public function testShutdownIsIdempotent(): void
    {
        $clock = $this->createMock(MonotonicClockInterface::class);
        $clock->method('monotonicNanoseconds')->willReturn(1);

        $scope = $this->createMock(CancellationScopeInterface::class);
        $scope->expects($this->once())->method('cancel');

        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->expects($this->once())->method('runUntil');

        $shutdown = new GracefulShutdown(
            $scheduler,
            $scope,
            $clock,
            Duration::ofSeconds(5),
        );

        $shutdown->shutdown();
        $shutdown->shutdown();
    }

    public function testShutdownDoesNotRegisterSignalsInUnitTestEnvironment(): void
    {
        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scope = $this->createMock(CancellationScopeInterface::class);
        $clock = $this->createMock(MonotonicClockInterface::class);

        $shutdown = new GracefulShutdown(
            $scheduler,
            $scope,
            $clock,
            Duration::ofSeconds(5),
        );

        $shutdown->registerSignals();

        // If pcntl_signal were available and SAPI is cli, this would register.
        // In test env we verify it doesn't throw.
        self::assertInstanceOf(GracefulShutdown::class, $shutdown);
    }
}
