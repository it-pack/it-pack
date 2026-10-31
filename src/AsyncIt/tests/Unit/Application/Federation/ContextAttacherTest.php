<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Application\Factory\ExecutionScopeFactoryInterface;
use ItPack\AsyncIt\Application\Federation\ContextAttacher;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stringable;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\ContextAttacher
 */
final class ContextAttacherTest extends TestCase
{
    public function testAttachesAndDetachesScopeAroundNext(): void
    {
        $flag = new DetachedFlag();

        $contextStorage = new ContextStorageFake($flag);
        $scopeFactory = new class () implements ExecutionScopeFactoryInterface {
            public function create(string|Stringable $discriminant): ExecutionScopeInterface
            {
                return new ReferenceExecutionScope((string) $discriminant);
            }
        };

        $attacher = new ContextAttacher($contextStorage, $scopeFactory);
        $task = new ContextAttachFakeTask('t1');

        $result = $attacher->process($task, fn (): string => 'done');

        self::assertSame('done', $result);
        self::assertTrue($flag->detached);
    }

    public function testDetachesOnException(): void
    {
        $flag = new DetachedFlag();

        $contextStorage = new ContextStorageFake($flag);
        $scopeFactory = new class () implements ExecutionScopeFactoryInterface {
            public function create(string|Stringable $discriminant): ExecutionScopeInterface
            {
                return new ReferenceExecutionScope((string) $discriminant);
            }
        };

        $attacher = new ContextAttacher($contextStorage, $scopeFactory);
        $task = new ContextAttachFakeTask('t1');

        $this->expectException(RuntimeException::class);
        $attacher->process($task, function (): string {
            throw new RuntimeException('boom');
        });

        self::assertTrue($flag->detached);
    }
}

final class DetachedFlag
{
    public bool $detached = false;
}

final readonly class ContextStorageFake implements ContextStorageInterface
{
    public function __construct(private DetachedFlag $flag)
    {
    }

    public function current(): ?ExecutionScopeInterface
    {
        return null;
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        return new ContextTokenFake();
    }

    public function detach(ContextTokenInterface $token): void
    {
        $this->flag->detached = true;
    }
}

final class ContextTokenFake implements ContextTokenInterface
{
    public function id(): int
    {
        return 0;
    }
}

final readonly class ContextAttachFakeTask implements TaskInterface
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
        return new ContextAttachFakeExecutionId();
    }

    public function cancellationScope(): CancellationScopeInterface
    {
        return new ContextAttachFakeCancellationScope();
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

final class ContextAttachFakeExecutionId implements ExecutionIdInterface
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

final class ContextAttachFakeCancellationScope implements CancellationScopeInterface
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
