<?php

declare(strict_types=1);

namespace Tests\Functional\Federation;

use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Application\Factory\TaskIdFactory;
use ItPack\AsyncIt\Application\Federation\MiddlewareScheduler;
use ItPack\AsyncIt\Contract\SchedulerMiddlewareInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use PHPUnit\Framework\TestCase;
use Tests\Functional\FunctionalTestHelper;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\MiddlewareScheduler
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class MiddlewareSchedulerTest extends TestCase
{
    public function testMiddlewarePipelineWrapsDispatch(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();
        $taskFactory = new TaskFactory(new TaskIdFactory(new RandomIdentifierGenerator()));
        $executionId = ExecutionId::fromString('middleware-test');

        $traceMiddleware = new class () implements SchedulerMiddlewareInterface {
            public array $calls = [];

            public function process(TaskInterface $task, callable $next): mixed
            {
                $this->calls[] = 'before-' . $task->id()->value;
                $result = $next();
                $this->calls[] = 'after-' . $task->id()->value;

                return $result;
            }
        };

        $middlewareScheduler = new MiddlewareScheduler(
            $scheduler,
            [$traceMiddleware],
        );

        $task = $taskFactory->create(
            $executionId,
            new CancellationScope()
        );
        $middlewareScheduler->dispatch($task, static fn (): string => 'dispatch result');

        self::assertNotEmpty($traceMiddleware->calls);
    }

    public function testMiddlewareOrderIsFirstRegisteredRunsFirst(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();
        $taskFactory = new TaskFactory(new TaskIdFactory(new RandomIdentifierGenerator()));
        $executionId = ExecutionId::fromString('middleware-test');

        $first = new class () implements SchedulerMiddlewareInterface {
            public array $order = [];

            public function process(TaskInterface $task, callable $next): mixed
            {
                $this->order[] = 'first-before';
                $result = $next();
                $this->order[] = 'first-after';

                return $result;
            }
        };
        $second = new class () implements SchedulerMiddlewareInterface {
            public array $order = [];

            public function process(TaskInterface $task, callable $next): mixed
            {
                $this->order[] = 'second-before';
                $result = $next();
                $this->order[] = 'second-after';

                return $result;
            }
        };

        $middlewareScheduler = new MiddlewareScheduler(
            $scheduler,
            [$first, $second],
        );

        $task = $taskFactory->create(
            $executionId,
            new CancellationScope()
        );
        $middlewareScheduler->dispatch($task, static fn (): string => 'dispatch result');

        self::assertSame(['first-before', 'first-after'], $first->order);
        self::assertSame(['second-before', 'second-after'], $second->order);
    }
}
