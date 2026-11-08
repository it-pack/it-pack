<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Activity;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\LifecycleExceptionInterface;
use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Exception\ConflictingActivityCompletionException;
use ItPack\RunIt\Domain\Exception\InvalidActivityTransitionException;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;

final class ActivityTest extends Unit
{
    public function testTerminalStatusesAreReported(): void
    {
        self::assertTrue(ActivityStatus::Succeeded->isTerminal());
        self::assertFalse(ActivityStatus::Running->isTerminal());
    }

    private function definition(): ActivityDefinition
    {
        return ActivityDefinition::create(
            ActivityName::fromString('http.call'),
            ActivityKind::fromString('client'),
        );
    }

    public function testDefinitionNormalizesWithAndWithoutAParentActivity(): void
    {
        $root = ActivityDefinition::create(
            ActivityName::fromString('http.call'),
            ActivityKind::fromString('client'),
        );

        $nested = ActivityDefinition::create(
            ActivityName::fromString('db.query'),
            ActivityKind::fromString('client'),
            ActivityId::fromString('act-parent'),
        );

        self::assertSame(
            ['name' => 'http.call', 'kind' => 'client', 'parent_activity_id' => null],
            $root->normalize(),
        );
        self::assertSame(
            ['name' => 'db.query', 'kind' => 'client', 'parent_activity_id' => 'act-parent'],
            $nested->normalize(),
        );
    }

    public function testStartProducesRunningActivityWithOwnIdentities(): void
    {
        $activity = Activity::start(
            ActivityId::fromString('act-1'),
            ExecutionId::fromString('exec-1'),
            $this->definition(),
            Instant::of(1000),
        );

        self::assertSame('act-1', $activity->id()->toString());
        self::assertSame('exec-1', $activity->executionId()->toString());
        self::assertSame('act-1', $activity->activityId()->toString());
        self::assertSame(ActivityStatus::Running, $activity->status());
        self::assertSame(1000, $activity->timestamp());
        self::assertSame([], $activity->attributes());
    }

    public function testCompleteReturnsNewTerminalActivity(): void
    {
        $running = Activity::start(
            ActivityId::fromString('act-1'),
            ExecutionId::fromString('exec-1'),
            $this->definition(),
            Instant::of(1000),
        );

        $done = $running->complete(
            ActivityStatus::Succeeded,
            throwableDescription: null,
            endedAt: Instant::of(2000),
        );

        self::assertSame(ActivityStatus::Succeeded, $done->status());
        self::assertSame(2000, $done->endedAt()?->epochSeconds());
        self::assertNull($done->throwableDescription());
        self::assertSame(ActivityStatus::Running, $running->status());
    }

    public function testReopeningATerminalActivityIsRejectedByAggregate(): void
    {
        $done = Activity::start(
            ActivityId::fromString('act-1'),
            ExecutionId::fromString('exec-1'),
            $this->definition(),
            Instant::of(1000),
        )->complete(
            ActivityStatus::Succeeded,
            throwableDescription: null,
            endedAt: Instant::of(2000),
        );

        try {
            $done->complete(
                ActivityStatus::Failed,
                ThrowableDescription::create(
                    'RuntimeException',
                    'late failure',
                    0,
                    previous: null,
                    trace: null
                ),
                Instant::of(3000),
            );
            self::fail('reopening a completed activity must be rejected by the aggregate itself');
        } catch (ConflictingActivityCompletionException $conflictingActivityCompletionException) {
            self::assertStringContainsString(
                '"succeeded"',
                $conflictingActivityCompletionException->getMessage()
            );
        }
    }

    public function testCompletingToANonTerminalStatusIsRejected(): void
    {
        $running = Activity::start(
            ActivityId::fromString('act-1'),
            ExecutionId::fromString('exec-1'),
            $this->definition(),
            Instant::of(1000),
        );

        try {
            $running->complete(
                ActivityStatus::Running,
                throwableDescription: null,
                endedAt: Instant::of(2000)
            );
            self::fail('completing to a non-terminal status must be rejected');
        } catch (InvalidActivityTransitionException $invalidActivityTransitionException) {
            self::assertStringContainsString(
                '"running" to "running"',
                $invalidActivityTransitionException->getMessage()
            );
        }
    }

    public function testTransitionMatrixMirrorsExecutionDiscipline(): void
    {
        self::assertTrue(ActivityStatus::Created->canTransitionTo(ActivityStatus::Running));
        self::assertTrue(ActivityStatus::Created->canTransitionTo(ActivityStatus::Cancelled));
        self::assertFalse(ActivityStatus::Created->canTransitionTo(ActivityStatus::Succeeded));

        foreach ([
            ActivityStatus::Succeeded,
            ActivityStatus::Failed,
            ActivityStatus::Cancelled,
            ActivityStatus::TimedOut,
        ] as $terminal) {
            self::assertTrue(ActivityStatus::Running->canTransitionTo($terminal), $terminal->label());
            self::assertFalse(
                $terminal->canTransitionTo(ActivityStatus::Running),
                'terminal statuses cannot transition'
            );
        }

        self::assertFalse(ActivityStatus::Running->canTransitionTo(ActivityStatus::Running));
    }

    public function testFailedActivityCarriesThrowableDescription(): void
    {
        $running = Activity::start(
            ActivityId::fromString('act-1'),
            ExecutionId::fromString('exec-1'),
            $this->definition(),
            Instant::of(1000),
        );

        $done = $running->complete(
            ActivityStatus::Failed,
            ThrowableDescription::create('RuntimeException', 'boom', 0, previous: null, trace: null),
            Instant::of(2000),
        );

        self::assertNotNull($done->throwableDescription());
    }

    public function testAttributesAreCaptured(): void
    {
        $activity = Activity::start(
            ActivityId::fromString('act-1'),
            ExecutionId::fromString('exec-1'),
            $this->definition(),
            Instant::of(1000),
            attributes: AttributeCollection::empty()->with('http.status', 200),
        );

        self::assertSame(['http.status' => 200], $activity->attributes());
    }

    public function testNormalizationIsDeterministic(): void
    {
        $activity = Activity::start(
            ActivityId::fromString('act-1'),
            ExecutionId::fromString('exec-1'),
            $this->definition(),
            Instant::of(1000),
        );

        $expected = [
            'id' => 'act-1',
            'execution_id' => 'exec-1',
            'name' => 'http.call',
            'kind' => 'client',
            'status' => 'running',
            'started_at' => 1000,
            'ended_at' => null,
            'parent_activity_id' => null,
            'sequence' => 0,
            'attributes' => [],
            'throwable' => null,
        ];

        self::assertSame($expected, $activity->normalize());
    }

    public function testParentActivityIdentityIsCaptured(): void
    {
        $definition = ActivityDefinition::create(
            ActivityName::fromString('child'),
            ActivityKind::fromString('step'),
            ActivityId::fromString('parent-act'),
        );

        $activity = Activity::start(
            ActivityId::fromString('act-2'),
            ExecutionId::fromString('exec-1'),
            $definition,
            Instant::of(1),
        );

        self::assertSame('parent-act', $activity->definition()->parentActivityId()?->toString());
    }
}
