<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Application\Federation\TraceContextInjector;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use PHPUnit\Framework\TestCase;
use WeakMap;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\TraceContextInjector
 */
final class TraceContextInjectorTest extends TestCase
{
    public function testInjectsTraceIdPerTask(): void
    {
        $idGenerator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                return 'trace-123';
            }
        };

        /** @var WeakMap<object, string> */
        $traceIds = new WeakMap();
        $injector = new TraceContextInjector($traceIds, $idGenerator);
        $task = new TraceContextFakeTask('t1');

        $result = $injector->process($task, fn (): string => 'done');

        self::assertSame('done', $result);
    }

    public function testGenerateIsCalledPerTask(): void
    {
        $callLog = new CallLog();
        $idGenerator = new readonly class ($callLog) implements IdentifierGeneratorInterface {
            public function __construct(
                private CallLog $callLog,
            ) {
            }

            public function generate(): string
            {
                $this->callLog->entries[] = 'generate';

                return 'trace-' . \count($this->callLog->entries);
            }
        };

        /** @var WeakMap<object, string> */
        $traceIds = new WeakMap();
        $injector = new TraceContextInjector($traceIds, $idGenerator);
        $task1 = new TraceContextFakeTask('t1');
        $task2 = new TraceContextFakeTask('t2');

        $injector->process($task1, fn (): string => 'r1');
        $injector->process($task2, fn (): string => 'r2');

        self::assertSame(['generate', 'generate'], $callLog->entries);
    }
}

final readonly class TraceContextFakeTask implements TaskInterface
{
    public function __construct(private string $id)
    {
    }

    public function id(): TaskIdInterface
    {
        return new TaskId($this->id);
    }

    public function executionId(): ExecutionIdInterface
    {
        return new TraceContextFakeExecutionId();
    }

    public function cancellationScope(): CancellationScopeInterface
    {
        return new TraceContextFakeCancellationScope();
    }

    public function status(): TaskStatus
    {
        return TaskStatus::Created;
    }

    public function transitionTo(TaskStatus $next): void
    {
    }

    public function complete(TaskStatus $outcome): void
    {
    }
}

final class TraceContextFakeExecutionId implements ExecutionIdInterface
{
    public function __toString(): string
    {
        return 'e1';
    }

    public function toString(): string
    {
        return 'e1';
    }

    public function equals(StringIdentifierInterface $other): bool
    {
        return $other instanceof self && $other->toString() === $this->toString();
    }
}

final class TraceContextFakeCancellationScope implements CancellationScopeInterface
{
    public function isCancelled(): bool
    {
        return false;
    }

    public function cancel(?CancellationReason $reason = null): void
    {
    }

    public function reason(): ?CancellationReason
    {
        return null;
    }

    public function throwIfCancelled(): void
    {
    }

    public function onCancel(callable $callback): void
    {
    }
}

final class CallLog
{
    /** @var array<string> */
    public array $entries = [];
}
