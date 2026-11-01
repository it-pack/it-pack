<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\ResultTypedSchedulerInterface;
use ItPack\AsyncIt\Contract\RetryPolicyInterface;
use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Contract\TaskResultInterface;
use ItPack\AsyncIt\Domain\TaskResult;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Domain\Shared\Duration;

final class FakeEngineScheduler implements EngineSchedulerInterface, ResultTypedSchedulerInterface
{
    public function dispatch(TaskInterface $task, callable $operation): mixed
    {
        return $operation();
    }

    public function submit(TaskInterface $task, callable $operation): void
    {
    }

    public function run(): void
    {
    }

    public function result(TaskInterface $task): mixed
    {
        return 'inner-result';
    }

    public function spawn(callable $operation, ?TaskIdInterface $id = null): TaskInterface
    {
        return new MiddlewareFakeTask('spawned');
    }

    public function await(TaskInterface $task): mixed
    {
        return null;
    }

    public function currentTask(): ?TaskInterface
    {
        return null;
    }

    public function awaitDelay(DurationInterface $duration): void
    {
    }

    public function retry(callable $operation, RetryPolicyInterface $policy): mixed
    {
        return $operation();
    }

    public function health(): SchedulerHealthInterface
    {
        return new MiddlewareFakeSchedulerHealth();
    }

    public function swapCapabilities(CapabilityDescriptorInterface $descriptor): void
    {
    }

    public function resultTyped(TaskInterface $task): TaskResultInterface
    {
        return TaskResult::success('inner-result', TaskStatus::Succeeded, Duration::ofNanoseconds(0));
    }
}
