<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Lifecycle;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Lifecycle\ExecutionLifecycleCoordinator;
use ItPack\RunIt\Domain\Exception\ConflictingCompletionException;
use ItPack\RunIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Execution\ExecutionSuccess;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;

final class ExecutionLifecycleCoordinatorTest extends Unit
{
    private function running(): Execution
    {
        return Execution::start(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1000),
        );
    }

    public function testCreatedMayTransitionToRunning(): void
    {
        $coordinator = new ExecutionLifecycleCoordinator();
        $created = Execution::create(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1)
        );
        self::assertSame(ExecutionStatus::Created, $created->status());

        $running = $coordinator->start($created);

        self::assertSame(ExecutionStatus::Running, $running->status());
    }

    public function testRunningToSucceededIsAllowed(): void
    {
        $coordinator = new ExecutionLifecycleCoordinator();
        $completed = $coordinator->transition(
            $this->running(),
            ExecutionSuccess::create(),
            Instant::of(2000)
        );

        self::assertSame(ExecutionStatus::Succeeded, $completed->status());
        self::assertSame(2000, $completed->endedAt()?->epochSeconds());
    }

    public function testRunningToFailedIsAllowed(): void
    {
        $coordinator = new ExecutionLifecycleCoordinator();
        $result = ExecutionFailure::of(
            ExecutionStatus::Failed,
            ThrowableDescription::create('X', 'boom', 0, previous: null, trace: null)
        );
        $completed = $coordinator->transition($this->running(), $result, Instant::of(2000));

        self::assertSame(ExecutionStatus::Failed, $completed->status());
    }

    public function testRunningToCancelledIsAllowed(): void
    {
        $coordinator = new ExecutionLifecycleCoordinator();
        $result = ExecutionFailure::of(
            ExecutionStatus::Cancelled,
            ThrowableDescription::create('X', 'cancelled', 0, previous: null, trace: null)
        );
        $completed = $coordinator->transition($this->running(), $result, Instant::of(2000));

        self::assertSame(ExecutionStatus::Cancelled, $completed->status());
    }

    public function testRunningToTimedOutIsAllowed(): void
    {
        $coordinator = new ExecutionLifecycleCoordinator();
        $result = ExecutionFailure::of(
            ExecutionStatus::TimedOut,
            ThrowableDescription::create('X', 'timeout', 0, previous: null, trace: null)
        );
        $completed = $coordinator->transition($this->running(), $result, Instant::of(2000));

        self::assertSame(ExecutionStatus::TimedOut, $completed->status());
    }

    public function testCreatedMayTransitionToCancelled(): void
    {
        $coordinator = new ExecutionLifecycleCoordinator();
        $created = Execution::create(
            ExecutionId::fromString('exec-3'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1)
        );
        $result = ExecutionFailure::of(
            ExecutionStatus::Cancelled,
            ThrowableDescription::create('X', 'cancelled', 0, previous: null, trace: null)
        );
        $completed = $coordinator->transition($created, $result, Instant::of(2));

        self::assertSame(ExecutionStatus::Cancelled, $completed->status());
    }

    public function testTransitionFromTerminalIsRejected(): void
    {
        $coordinator = new ExecutionLifecycleCoordinator();
        $completed = $coordinator->transition(
            $this->running(),
            ExecutionSuccess::create(),
            Instant::of(2000)
        );

        $this->expectException(ConflictingCompletionException::class);
        // A terminal execution cannot transition to a different terminal status.
        $coordinator->transition(
            $completed,
            ExecutionFailure::of(
                ExecutionStatus::Failed,
                ThrowableDescription::create('X', 'e', 0, previous: null, trace: null)
            ),
            Instant::of(3000)
        );
    }

    public function testRepeatedSameCompletionIsIdempotent(): void
    {
        $coordinator = new ExecutionLifecycleCoordinator();
        $first = $coordinator->transition($this->running(), ExecutionSuccess::create(), Instant::of(2000));
        $second = $coordinator->transition($first, ExecutionSuccess::create(), Instant::of(3000));

        self::assertSame($first, $second, 'Repeating the same terminal completion must be idempotent.');
    }

    public function testConflictingCompletionRaises(): void
    {
        $coordinator = new ExecutionLifecycleCoordinator();
        $succeeded = $coordinator->transition(
            $this->running(),
            ExecutionSuccess::create(),
            Instant::of(2000)
        );

        $this->expectException(ConflictingCompletionException::class);
        $coordinator->transition(
            $succeeded,
            ExecutionFailure::of(
                ExecutionStatus::Failed,
                ThrowableDescription::create('X', 'e', 0, previous: null, trace: null)
            ),
            Instant::of(3000)
        );
    }

    public function testIllegalTransitionRejected(): void
    {
        $coordinator = new ExecutionLifecycleCoordinator();

        $this->expectException(InvalidLifecycleTransitionException::class);
        // created -> succeeded is illegal because it skips running
        $created = Execution::create(
            ExecutionId::fromString('exec-2'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1)
        );
        $coordinator->transition($created, ExecutionSuccess::create(), Instant::of(2));
    }

    public function testTransitionMatrix(): void
    {
        $coordinator = new ExecutionLifecycleCoordinator();

        // From Created only Running is allowed.
        self::assertTrue(ExecutionLifecycleCoordinator::isAllowed(ExecutionStatus::Created, ExecutionStatus::Running));
        self::assertFalse(ExecutionLifecycleCoordinator::isAllowed(
            ExecutionStatus::Created,
            ExecutionStatus::Succeeded
        ));

        // From Running every terminal outcome is allowed.
        foreach ([
            ExecutionStatus::Succeeded,
            ExecutionStatus::Failed,
            ExecutionStatus::Cancelled,
            ExecutionStatus::TimedOut,
        ] as $terminal) {
            self::assertTrue(
                ExecutionLifecycleCoordinator::isAllowed(ExecutionStatus::Running, $terminal),
                $terminal->label()
            );
        }

        // Terminal statuses cannot transition further.
        self::assertFalse(ExecutionLifecycleCoordinator::isAllowed(
            ExecutionStatus::Succeeded,
            ExecutionStatus::Failed
        ));
        self::assertFalse(ExecutionLifecycleCoordinator::isAllowed(
            ExecutionStatus::Failed,
            ExecutionStatus::Succeeded
        ));

        // The matrix excludes idempotent repeats: they are coordinator no-ops,
        // not transitions. transition() still treats them as idempotent.
        self::assertFalse(ExecutionLifecycleCoordinator::isAllowed(
            ExecutionStatus::Succeeded,
            ExecutionStatus::Succeeded
        ));
        self::assertFalse(ExecutionLifecycleCoordinator::isAllowed(ExecutionStatus::Failed, ExecutionStatus::Failed));

        $terminal = Execution::start(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1),
        )->complete(ExecutionSuccess::create(), Instant::of(2));
        self::assertSame(
            $terminal,
            $coordinator->transition($terminal, ExecutionSuccess::create(), Instant::of(3)),
            'same-status repeat is an idempotent no-op'
        );
    }

}
