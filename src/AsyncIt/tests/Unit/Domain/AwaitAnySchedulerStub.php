<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\RetryPolicyInterface;
use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * Scheduler stub that exposes `awaitAny()` for SelectGroup's awaitAny branch.
 *
 * All `EngineSchedulerInterface` methods not exercised by SelectGroup::await()
 * are no-ops returning defaults.
 */
final readonly class AwaitAnySchedulerStub implements EngineSchedulerInterface
{
    public function __construct(
        private TaskInterface $spawnResult,
        private string $awaitAnyResult,
        private mixed $resultValue,
    ) {
    }

    public function submit(TaskInterface $task, callable $operation): void
    {
    }

    public function run(): void
    {
    }

    public function result(TaskInterface $task): mixed
    {
        return $this->resultValue;
    }

    public function dispatch(TaskInterface $task, callable $operation): mixed
    {
        return null;
    }

    public function spawn(callable $operation, ?TaskIdInterface $id = null): TaskInterface
    {
        return $this->spawnResult;
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
        return null;
    }

    public function health(): SchedulerHealthInterface
    {
        return new class () implements SchedulerHealthInterface {
            public function isHealthy(): bool
            {
                return true;
            }

            public function pendingTaskCount(): int
            {
                return 0;
            }

            public function activeHandleCount(): int
            {
                return 0;
            }

            public function uptime(): DurationInterface
            {
                return new class () implements DurationInterface {
                    public function nanoseconds(): int
                    {
                        return 0;
                    }

                    public function seconds(): int
                    {
                        return 0;
                    }

                    public function toString(): string
                    {
                        return '0s';
                    }
                };
            }

            public function recentErrors(int $n): array
            {
                return [];
            }
        };
    }

    public function swapCapabilities(CapabilityDescriptorInterface $descriptor): void
    {
    }

    /**
     * @param list<TaskInterface> $tasks
     */
    public function awaitAny(array $tasks): string
    {
        return $this->awaitAnyResult;
    }
}
