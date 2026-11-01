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
use RuntimeException;

final class FakeScheduler implements EngineSchedulerInterface, ResultTypedSchedulerInterface
{
    /** @var list<string> */
    private array $calls = [];

    public function __construct(
        private readonly string $label,
        private readonly bool $shouldThrow = false,
    ) {
    }

    /** @return list<string> */
    public function calls(): array
    {
        return $this->calls;
    }

    public function submit(TaskInterface $task, callable $operation): void
    {
        $this->calls[] = $this->label . '.submit';
    }

    public function run(): void
    {
        $this->calls[] = $this->label . '.run';
    }

    public function result(TaskInterface $task): mixed
    {
        $this->calls[] = $this->label . '.result';

        return 'result';
    }

    public function dispatch(TaskInterface $task, callable $operation): mixed
    {
        $this->calls[] = $this->label . '.dispatch';

        if ($this->shouldThrow) {
            throw new RuntimeException('boom');
        }

        return $operation();
    }

    public function spawn(callable $operation, ?TaskIdInterface $id = null): TaskInterface
    {
        $this->calls[] = $this->label . '.spawn';

        return new FakeTask('spawned');
    }

    public function await(TaskInterface $task): mixed
    {
        $this->calls[] = $this->label . '.await';

        return 'awaited';
    }

    public function currentTask(): ?TaskInterface
    {
        $this->calls[] = $this->label . '.currentTask';

        return null;
    }

    public function awaitDelay(DurationInterface $duration): void
    {
        $this->calls[] = $this->label . '.awaitDelay';
    }

    public function retry(callable $operation, RetryPolicyInterface $policy): mixed
    {
        $this->calls[] = $this->label . '.retry';

        return $operation();
    }

    public function health(): SchedulerHealthInterface
    {
        $this->calls[] = $this->label . '.health';

        return new FakeHealth();
    }

    public function swapCapabilities(CapabilityDescriptorInterface $descriptor): void
    {
        $this->calls[] = $this->label . '.swapCapabilities';
    }

    public function resultTyped(TaskInterface $task): TaskResultInterface
    {
        $this->calls[] = $this->label . '.resultTyped';

        return TaskResult::success('result', TaskStatus::Succeeded, Duration::ofNanoseconds(0));
    }
}
