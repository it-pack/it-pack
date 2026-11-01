<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Application\Federation\MiddlewareScheduler;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\MiddlewareScheduler
 */
final class MiddlewareSchedulerTest extends TestCase
{
    public function testDispatchRunsMiddlewaresInOrderThenInner(): void
    {
        $log = new OrderLog();
        $inner = new FakeEngineScheduler();
        $mw1 = new OrderedMiddleware($log, 'mw1');
        $mw2 = new OrderedMiddleware($log, 'mw2');

        $scheduler = new MiddlewareScheduler($inner, [$mw1, $mw2]);
        $task = new MiddlewareFakeTask('t1');

        $result = $scheduler->dispatch($task, fn (): string => 'done');

        self::assertSame('done', $result);
        self::assertSame(
            ['mw1-before', 'mw2-before', 'mw2-after', 'mw1-after'],
            $log->entries,
        );
    }

    public function testMiddlewareCanShortCircuitWithoutCallingNext(): void
    {
        $inner = new FakeEngineScheduler();
        $shortCircuit = new ShortCircuitMiddleware();
        $scheduler = new MiddlewareScheduler($inner, [$shortCircuit]);
        $task = new MiddlewareFakeTask('t1');

        self::assertSame('short-circuited', $scheduler->dispatch($task, fn (): string => 'never-called'));
    }

    public function testNonDispatchMethodsDelegateToInner(): void
    {
        $inner = new FakeEngineScheduler();
        $scheduler = new MiddlewareScheduler($inner);
        $task = new MiddlewareFakeTask('t1');

        self::assertSame('inner-result', $scheduler->result($task));

        $health = $scheduler->health();
        self::assertTrue($health->isHealthy());
        self::assertSame(0, $health->pendingTaskCount());
        self::assertSame(0, $health->activeHandleCount());
        self::assertSame(0, $health->uptime()->nanoseconds());
        self::assertSame([], $health->recentErrors(10));
    }
}
