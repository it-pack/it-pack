<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Application\Federation\BackpressureGate;
use ItPack\AsyncIt\Contract\Port\BackpressurePolicy;
use ItPack\AsyncIt\Domain\Exception\BackpressureExceededException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\BackpressureGate
 */
final class BackpressureGateTest extends TestCase
{
    public function testErrorPolicyThrowsAtCapacity(): void
    {
        $gate = new BackpressureGate(BackpressurePolicy::Error, 0);
        $task = new BackpressureFakeTask('t1');

        $this->expectException(BackpressureExceededException::class);
        $gate->process($task, fn (): string => 'never');
    }

    public function testDropPolicyThrowsAtCapacity(): void
    {
        $gate = new BackpressureGate(BackpressurePolicy::Drop, 0);
        $task = new BackpressureFakeTask('t1');

        $this->expectException(BackpressureExceededException::class);
        $gate->process($task, fn (): string => 'never');
    }

    public function testBlockPolicyThrowsAtCapacity(): void
    {
        $gate = new BackpressureGate(BackpressurePolicy::Block, 0);
        $task = new BackpressureFakeTask('t1');

        $this->expectException(BackpressureExceededException::class);
        $gate->process($task, fn (): string => 'never');
    }

    public function testBelowCapacityAlwaysProceeds(): void
    {
        $gate = new BackpressureGate(BackpressurePolicy::Error, 2);
        $task1 = new BackpressureFakeTask('t1');
        $task2 = new BackpressureFakeTask('t2');

        self::assertSame('first', $gate->process($task1, fn (): string => 'first'));
        self::assertSame('second', $gate->process($task2, fn (): string => 'second'));
    }

    public function testSlotReleasedAfterDispatch(): void
    {
        $gate = new BackpressureGate(BackpressurePolicy::Error, 1);
        $task1 = new BackpressureFakeTask('t1');
        $task2 = new BackpressureFakeTask('t2');

        self::assertSame('first', $gate->process($task1, fn (): string => 'first'));
        self::assertSame('second', $gate->process($task2, fn (): string => 'second'));
    }
}
