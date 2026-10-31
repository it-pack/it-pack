<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Application\Factory\CancellationScopeFactoryInterface;
use ItPack\AsyncIt\Application\Federation\FederatedScheduler;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\Port\RemoteSchedulerPortInterface;
use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\AsyncIt\Contract\RemoteTaskInterface;
use ItPack\AsyncIt\Domain\RemoteTask;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\FederatedScheduler
 */
final class FederatedSchedulerTest extends TestCase
{
    public function testSubmitRoutesTaskToPortAndReturnsPromise(): void
    {
        $port = new FakeRemoteSchedulerPort();
        $idGenerator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                return 'gen-123';
            }
        };
        $scopeFactory = new class () implements CancellationScopeFactoryInterface {
            public function create(): CancellationScopeInterface
            {
                return new NotCancelledScope();
            }
        };

        $scheduler = new FederatedScheduler($port, $idGenerator, $scopeFactory);
        $task = new RemoteTask(
            id: new TaskId('remote-1'),
            operation: 'App\Job\SendEmail',
            payload: ['to' => 'user@example.com'],
            priority: 5,
        );

        $promise = $scheduler->submit($task);

        self::assertInstanceOf(PromiseInterface::class, $promise);
        self::assertSame('remote-result', $promise->get());
        self::assertNotNull($port->lastTask);
        self::assertSame('App\Job\SendEmail', $port->lastTask->operation());
        $payload = $port->lastTask->payload();
        self::assertArrayHasKey('to', $payload);
        self::assertSame('user@example.com', $payload['to']);
        self::assertArrayHasKey('cancellation_token_scope_id', $payload);
        self::assertArrayHasKey('cancellation_token_reason', $payload);
        self::assertArrayHasKey('cancellation_token_cancelled', $payload);
        self::assertArrayHasKey('cancellation_token_bound_to', $payload);
    }

    public function testSubmitPreservesTaskFields(): void
    {
        $port = new FakeRemoteSchedulerPort();
        $idGenerator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                return 'gen-456';
            }
        };
        $scopeFactory = new class () implements CancellationScopeFactoryInterface {
            public function create(): CancellationScopeInterface
            {
                return new NotCancelledScope();
            }
        };

        $scheduler = new FederatedScheduler($port, $idGenerator, $scopeFactory);
        $deadline = Duration::ofSeconds(600);
        $task = new RemoteTask(
            id: new TaskId('remote-2'),
            operation: 'App\Job\ProcessPayment',
            payload: ['amount' => 99.99],
            deadline: $deadline,
            priority: 10,
            correlationId: 'corr-xyz',
        );

        $scheduler->submit($task);

        self::assertNotNull($port->lastTask);
        self::assertSame('remote-2', $port->lastTask->id()->value);
        self::assertSame('App\Job\ProcessPayment', $port->lastTask->operation());
        $payload = $port->lastTask->payload();
        self::assertArrayHasKey('amount', $payload);
        self::assertSame(99.99, $payload['amount']);
        self::assertArrayHasKey('cancellation_token_scope_id', $payload);
        self::assertArrayHasKey('cancellation_token_reason', $payload);
        self::assertArrayHasKey('cancellation_token_cancelled', $payload);
        self::assertArrayHasKey('cancellation_token_bound_to', $payload);
        self::assertSame('corr-xyz', $payload['cancellation_token_bound_to']);
        $deadlineFromTask = $port->lastTask->deadline();
        self::assertNotNull($deadlineFromTask);
        self::assertTrue($deadline->equals($deadlineFromTask));
        self::assertSame(10, $port->lastTask->priority());
        self::assertSame('corr-xyz', $port->lastTask->correlationId());
    }

    public function testSubmitEmbedsCancellationTokenInPayload(): void
    {
        $port = new FakeRemoteSchedulerPort();
        $idGenerator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                return 'gen-789';
            }
        };
        $scopeFactory = new class () implements CancellationScopeFactoryInterface {
            public function create(): CancellationScopeInterface
            {
                return new NotCancelledScope();
            }
        };

        $scheduler = new FederatedScheduler($port, $idGenerator, $scopeFactory);
        $task = new RemoteTask(
            id: new TaskId('remote-3'),
            operation: 'App\Job\AnyJob',
        );

        $scheduler->submit($task);

        self::assertNotNull($port->lastTask);
        $payload = $port->lastTask->payload();
        self::assertArrayHasKey('cancellation_token_scope_id', $payload);
        self::assertIsString($payload['cancellation_token_scope_id']);
        self::assertArrayHasKey('cancellation_token_reason', $payload);
        self::assertNull($payload['cancellation_token_reason']);
        self::assertArrayHasKey('cancellation_token_cancelled', $payload);
        self::assertFalse($payload['cancellation_token_cancelled']);
        self::assertArrayHasKey('cancellation_token_bound_to', $payload);
    }

    public function testSubmitWithExistingTaskIdReusesIt(): void
    {
        $port = new FakeRemoteSchedulerPort();
        $idGenerator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                return 'unused';
            }
        };
        $scopeFactory = new class () implements CancellationScopeFactoryInterface {
            public function create(): CancellationScopeInterface
            {
                return new NotCancelledScope();
            }
        };

        $scheduler = new FederatedScheduler($port, $idGenerator, $scopeFactory);
        $task = new RemoteTask(
            id: new TaskId('existing-id'),
            operation: 'App\Job\ReuseIdJob',
        );

        $scheduler->submit($task);

        self::assertNotNull($port->lastTask);
        self::assertSame('existing-id', $port->lastTask->id()->value);
    }

    public function testPromiseIsDoneAndReturnsResult(): void
    {
        $port = new FakeRemoteSchedulerPort();
        $idGenerator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                return 'gen';
            }
        };
        $scopeFactory = new class () implements CancellationScopeFactoryInterface {
            public function create(): CancellationScopeInterface
            {
                return new NotCancelledScope();
            }
        };

        $scheduler = new FederatedScheduler($port, $idGenerator, $scopeFactory);
        $task = new RemoteTask(id: new TaskId('remote-4'), operation: 'App\Job\DoneJob');

        $promise =         $scheduler->submit($task);

        self::assertNotNull($port->lastTask);
        self::assertTrue($promise->isDone());
        self::assertTrue($promise->wait());
        self::assertSame('remote-result', $promise->get());
    }
}

final class FakeRemoteSchedulerPort implements RemoteSchedulerPortInterface
{
    public RemoteTaskInterface|null $lastTask = null;

    public function execute(RemoteTaskInterface $task): PromiseInterface
    {
        $this->lastTask = $task;

        return new ResolvedPromise('remote-result');
    }
}

final readonly class NotCancelledScope implements CancellationScopeInterface
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

final readonly class ResolvedPromise implements PromiseInterface
{
    public function __construct(
        private mixed $value,
    ) {
    }

    public function isDone(): bool
    {
        return true;
    }

    public function wait(?DurationInterface $timeout = null): bool
    {
        return true;
    }

    public function get(): mixed
    {
        return $this->value;
    }
}
