<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Runner;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Application\Lifecycle\ExecutionLifecycleCoordinator;
use ItPack\RunIt\Application\Runner\DefaultExecutionRunner;
use ItPack\RunIt\Application\Scope\ExecutionScope;
use ItPack\RunIt\Contract\Application\ExecutionLifecycleCoordinatorInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionSnapshotFactoryInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;
use ItPack\RunIt\Contract\Domain\ExecutionSnapshotInterface;
use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Exception\ConflictingCompletionException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Execution\ExecutionSuccess;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use stdClass;
use Tests\Support\Fixture\RunItTestKit;
use Throwable;

#[CoversClass(DefaultExecutionRunner::class)]
final class CombinedLifecycleFailureTest extends Unit
{
    /** @return iterable<string, array{bool, string}> */
    public static function failures(): iterable
    {
        yield 'successful business, terminal publication failure' => [false, 'execution'];
        yield 'throwing business, snapshot publication failure' => [true, 'snapshot'];
        yield 'throwing business, snapshot factory failure and health re-entry' => [true, 'factory'];
    }

    #[DataProvider('failures')]
    public function testNestedOpenActivityAndRepeatedCompletionSurviveFailures(bool $businessFails, string $fail): void
    {
        $publisher = new class ($fail) implements ArtifactPublisherInterface {
            /** @var list<string> */
            public array $attempts = [];

            public bool $healthReentry = false;

            public int $reentryAttempts = 0;

            public function __construct(private readonly string $fail)
            {
            }

            public function publish(ExecutionArtifactInterface $artifact): void
            {
                if ($this->healthReentry && $artifact instanceof ObservationInterface) {
                    ++$this->reentryAttempts;
                    throw new RuntimeException('re-entry adapter failed');
                }

                if ($artifact instanceof ExecutionInterface && $artifact->status()->isTerminal()) {
                    $kind = 'execution';
                } elseif ($artifact instanceof ExecutionSnapshotInterface) {
                    $kind = 'snapshot';
                } else {
                    return;
                }

                $this->attempts[] = $artifact->executionId()->toString() . ':' . $kind;
                if ($kind === $this->fail) {
                    throw new RuntimeException('adapter failed');
                }
            }
        };
        $coordinator = new class () implements ExecutionLifecycleCoordinatorInterface {
            /** @var list<ExecutionStatusInterface> */
            public array $statuses = [];

            /** @var list<bool> */
            public array $same = [];

            public int $conflicts = 0;

            private readonly ExecutionLifecycleCoordinator $inner;

            public function __construct()
            {
                $this->inner = new ExecutionLifecycleCoordinator();
            }

            public function start(ExecutionInterface $execution): ExecutionInterface
            {
                return $this->inner->start($execution);
            }

            public function transition(
                ExecutionInterface $execution,
                ExecutionResultInterface $result,
                InstantInterface $endedAt,
            ): ExecutionInterface {
                $completed = $this->inner->transition($execution, $result, $endedAt);
                $this->statuses[] = $completed->status();
                $this->same[] = $completed === $this->inner->transition($completed, $result, $endedAt);
                $opposite = $completed->status() === ExecutionStatus::Succeeded
                    ? ExecutionFailure::of(
                        ExecutionStatus::Failed,
                        ThrowableDescription::create('X', 'conflict', 0, previous: null, trace: null),
                    )
                    : ExecutionSuccess::create();
                try {
                    $this->inner->transition($completed, $opposite, $endedAt);
                } catch (ConflictingCompletionException) {
                    ++$this->conflicts;
                }

                return $completed;
            }
        };
        $reports = 0;
        $kit = null;
        $snapshotFactory = null;
        if ($fail === 'factory') {
            $snapshotFactory = self::createStub(ExecutionSnapshotFactoryInterface::class);
            $snapshotFactory->method('terminal')->willThrowException(new RuntimeException('snapshot factory failed'));
        }

        $kit = new RunItTestKit(
            publisher: $publisher,
            onPublicationFailure: static function (Throwable $failure) use (&$reports, &$kit, $publisher, $fail): void {
                ++$reports;
                if ($fail === 'factory') {
                    if ($kit instanceof RunItTestKit) {
                        $publisher->healthReentry = true;
                        try {
                            $kit->storage->current()?->observations()->record('health re-entry');
                        } finally {
                            $publisher->healthReentry = false;
                        }
                    }

                    return;
                }

                throw new RuntimeException('health failed');
            },
            coordinator: $coordinator,
            snapshotFactory: $snapshotFactory,
        );
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('operation'),
            ExecutionKind::fromString('task'),
        );
        $value = new stdClass();
        $businessError = new RuntimeException('business failed');
        $childCaught = null;
        $restored = false;
        $result = $kit->runner->run($definition, static function (ExecutionScopeInterface $parent) use (
            $kit,
            $definition,
            $value,
            $businessError,
            $businessFails,
            &$childCaught,
            &$restored
        ): object {
            try {
                $childResult = $kit->runner->run($definition, static function (ExecutionScopeInterface $child) use (
                    $value,
                    $businessError,
                    $businessFails
                ): object {
                    self::assertInstanceOf(ExecutionScope::class, $child);
                    $child->trackOpenActivity(Activity::start(
                        ActivityId::fromString('open-activity'),
                        $child->execution()->id(),
                        ActivityDefinition::create(ActivityName::fromString('open'), ActivityKind::fromString('task')),
                        Instant::of(1),
                    ));
                    if ($businessFails) {
                        throw $businessError;
                    }

                    return $value;
                });
            } catch (Throwable $throwable) {
                $childCaught = $throwable;
                $childResult = null;
            }

            $restored = $kit->storage->current() === $parent;

            return $childResult ?? $value;
        });

        self::assertSame($value, $result);
        self::assertSame($businessFails ? $businessError : null, $childCaught);
        self::assertTrue($restored);
        self::assertNull($kit->storage->current());
        self::assertSame(
            [$businessFails ? ExecutionStatus::Failed : ExecutionStatus::Succeeded, ExecutionStatus::Succeeded],
            $coordinator->statuses,
        );
        self::assertSame([true, true], $coordinator->same);
        self::assertSame(2, $coordinator->conflicts);
        self::assertSame(
            $fail === 'factory'
                ? ['exec-2:execution', 'exec-1:execution']
                : ['exec-2:execution', 'exec-2:snapshot', 'exec-1:execution', 'exec-1:snapshot'],
            $publisher->attempts,
        );
        self::assertGreaterThanOrEqual(2, $reports);
        self::assertLessThanOrEqual(2, $reports);
        self::assertSame($fail === 'factory' ? 2 : 0, $publisher->reentryAttempts);
    }
}
