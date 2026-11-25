<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Recorder;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Recorder\DefaultObservationRecorder;
use ItPack\RunIt\Application\Runner\DefaultExecutionRunner;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\Support\Fixture\DeterministicMonotonicClock;
use Tests\Support\Fixture\RecordingArtifactPublisher;
use Tests\Support\Fixture\RunItTestKit;
use Tests\Support\Fixture\TestObservationIdFactory;
use Throwable;

/**
 * @group application
 * @group recorder
 */
#[CoversClass(DefaultObservationRecorder::class)]
#[UsesClass(DefaultExecutionRunner::class)]
final class DefaultObservationRecorderTest extends Unit
{
    private RunItTestKit $kit;

    protected function setUp(): void
    {
        $this->kit = new RunItTestKit();
    }

    /**
     * @return list<ObservationInterface>
     */
    private function observations(): array
    {
        return $this->kit->artifactsOf(ObservationInterface::class);
    }

    public function testObservationWithoutActivityBindsExecutionOnly(): void
    {
        $this->kit->runner->run(
            ExecutionDefinition::create(
                ExecutionName::fromString('op'),
                ExecutionKind::fromString('task')
            ),
            function ($scope): null {
                $scope->observations()->record('plain message', ObservationSeverity::Warning);

                return null;
            },
        );

        $observations = $this->observations();
        self::assertCount(1, $observations);
        self::assertSame('plain message', $observations[0]->message());
        self::assertSame(ObservationSeverity::Warning, $observations[0]->severity());
        self::assertNull($observations[0]->activityId());
    }

    public function testObservationInsideActivityBindsActivity(): void
    {
        $this->kit->runner->run(
            ExecutionDefinition::create(
                ExecutionName::fromString('op'),
                ExecutionKind::fromString('task')
            ),
            function ($scope): null {
                $scope->activities()->run(
                    ActivityDefinition::create(
                        ActivityName::fromString('load'),
                        ActivityKind::fromString('io')
                    ),
                    function ($activityScope): null {
                        $activityScope->observations()->record('in activity', ObservationSeverity::Info);

                        return null;
                    },
                );

                return null;
            },
        );

        $observations = $this->observations();
        self::assertCount(1, $observations);
        self::assertNotNull(
            $observations[0]->activityId(),
            'Observations inside an activity must bind the activity.'
        );
    }

    public function testThrowableIsNormalizedThroughMapper(): void
    {
        $error = new RuntimeException('boom');

        $this->kit->runner->run(
            ExecutionDefinition::create(
                ExecutionName::fromString('op'),
                ExecutionKind::fromString('task')
            ),
            function ($scope) use ($error): null {
                $scope->observations()->record(
                    'failed step',
                    ObservationSeverity::Error,
                    null,
                    $error,
                    'step.failed'
                );

                return null;
            },
        );

        $observations = $this->observations();
        self::assertCount(1, $observations);
        self::assertSame('step.failed', $observations[0]->diagnosticCode());
        self::assertNotNull($observations[0]->throwableDescription());
        self::assertSame('boom', $observations[0]->throwableDescription()->message());
    }

    public function testDebugSeverity(): void
    {
        $recorder = $this->directRecorder();
        $observation = $recorder->record('debug msg', ObservationSeverity::Debug);

        self::assertSame(ObservationSeverity::Debug, $observation->severity());
        self::assertSame('debug msg', $observation->message());
    }

    public function testNoticeSeverity(): void
    {
        $recorder = $this->directRecorder();
        $observation = $recorder->record('notice msg', ObservationSeverity::Notice);

        self::assertSame(ObservationSeverity::Notice, $observation->severity());
    }

    public function testCriticalSeverity(): void
    {
        $recorder = $this->directRecorder();
        $observation = $recorder->record('critical msg', ObservationSeverity::Critical);

        self::assertSame(ObservationSeverity::Critical, $observation->severity());
    }

    public function testAlertSeverity(): void
    {
        $recorder = $this->directRecorder();
        $observation = $recorder->record('alert msg', ObservationSeverity::Alert);

        self::assertSame(ObservationSeverity::Alert, $observation->severity());
    }

    public function testEmergencySeverity(): void
    {
        $recorder = $this->directRecorder();
        $observation = $recorder->record('emergency msg', ObservationSeverity::Emergency);

        self::assertSame(ObservationSeverity::Emergency, $observation->severity());
    }

    public function testUnknownSeverityFallsBackToInfo(): void
    {
        $severity = new readonly class ('unknown', 99) implements ObservationSeverityInterface {
            public function __construct(private string $label, private int $level)
            {
            }

            public function label(): string
            {
                return $this->label;
            }

            public function level(): int
            {
                return $this->level;
            }

            public function isError(): bool
            {
                return false;
            }
        };
        $recorder = $this->directRecorder();
        $observation = $recorder->record('fallback msg', $severity);

        self::assertSame(ObservationSeverity::Info, $observation->severity());
    }

    public function testShouldDropSkipsPublishing(): void
    {
        $publisher = new RecordingArtifactPublisher();
        $recorder = new DefaultObservationRecorder(
            ExecutionId::fromString('exec-1'),
            fn (): null => null,
            new InMemorySequenceAllocator(),
            new DeterministicMonotonicClock(1000, 0),
            $publisher,
            $this->throwableMapper(),
            new TestObservationIdFactory(),
            authority: new ProvenanceAuthority(),
            shouldDrop: fn (): bool => true,
        );

        $observation = $recorder->record('dropped', ObservationSeverity::Info);

        self::assertCount(0, $publisher->published());
        self::assertSame('dropped', $observation->message());
    }

    public function testLegacyActivityPathReturnsNullExportId(): void
    {
        $publisher = new RecordingArtifactPublisher();
        $recorder = new DefaultObservationRecorder(
            ExecutionId::fromString('exec-1'),
            fn (): ActivityIdInterface => ActivityId::fromString('act-1'),
            new InMemorySequenceAllocator(),
            new DeterministicMonotonicClock(1000, 0),
            $publisher,
            $this->throwableMapper(),
            new TestObservationIdFactory(),
        );

        $observation = $recorder->record('msg', ObservationSeverity::Info);

        self::assertSame('act-1', $observation->activityId()?->toString());
        self::assertSame(1, count($publisher->published()));
    }

    private function directRecorder(): DefaultObservationRecorder
    {
        return new DefaultObservationRecorder(
            ExecutionId::fromString('exec-1'),
            fn (): null => null,
            new InMemorySequenceAllocator(),
            new DeterministicMonotonicClock(1000, 0),
            new RecordingArtifactPublisher(),
            $this->throwableMapper(),
            new TestObservationIdFactory(),
        );
    }

    private function throwableMapper(): ThrowableMapperInterface
    {
        return new class () implements ThrowableMapperInterface {
            public function describe(Throwable $throwable): ThrowableDescriptionInterface
            {
                $class = $throwable::class;
                $message = $throwable->getMessage();

                return new readonly class ($class, $message) implements ThrowableDescriptionInterface {
                    public function __construct(private string $class, private string $message)
                    {
                    }

                    public function class(): string
                    {
                        return $this->class;
                    }

                    public function message(): string
                    {
                        return $this->message;
                    }

                    public function code(): int
                    {
                        return 0;
                    }

                    public function previous(): ?self
                    {
                        return null;
                    }

                    public function trace(): ?string
                    {
                        return null;
                    }

                    public function toArray(): array
                    {
                        return ['message' => $this->message];
                    }
                };
            }
        };
    }
}
