<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Scheduler;

use Fiber;
use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\PollerFactory;
use ItPack\AsyncIt\Application\Factory\ReferenceExecutionScopeFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactory;
use ItPack\AsyncIt\Contract\CapabilityDescriptor;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Application\Publisher\NullArtifactPublisher;
use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the reference reactor: cooperative suspend/resume, terminal
 * lifecycle transitions, and observed failure handling (publish + rethrow).
 *
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class FiberTaskSchedulerTest extends TestCase
{
    private function scheduler(): FiberTaskScheduler
    {
        return new FiberTaskScheduler(
            new FiberFactory(),
            new TaskRecordFactory(),
            (new PollerFactory())->create(new CapabilityDescriptor([
                CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Emulated,
            ])),
            new MemoryContextStorage(),
            new ReferenceExecutionScopeFactory(),
            new NullArtifactPublisher(),
        );
    }

    private function task(): \ItPack\AsyncIt\Contract\TaskInterface
    {
        return (new TaskFactory(new RandomIdentifierGenerator()))
            ->create(ExecutionId::fromString('exec-1'), (new CancellationScopeFactory())->create());
    }

    public function testRunsTaskToCompletionWithSuspendResume(): void
    {
        $scheduler = $this->scheduler();
        $log = [];
        $task = $this->task();

        $scheduler->submit($task, static function () use (&$log): void {
            $log[] = 'a';
            Fiber::suspend();
            $log[] = 'b';
        });

        $scheduler->run();

        self::assertSame(['a', 'b'], $log);
        self::assertSame(TaskStatus::Succeeded, $task->status());
    }

    public function testThrowingTaskIsMarkedFailedAndRethrowsOriginal(): void
    {
        $scheduler = $this->scheduler();
        $task = $this->task();

        $scheduler->submit($task, static function (): void {
            throw new \RuntimeException('boom');
        });

        $thrown = null;
        try {
            $scheduler->run();
        } catch (\RuntimeException $error) {
            $thrown = $error;
        }

        self::assertNotNull($thrown, 'The original exception must be rethrown.');
        self::assertSame('boom', $thrown->getMessage());
        self::assertSame(TaskStatus::Failed, $task->status());
    }
}
