<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Policy;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Application\Policy\AutoCancelOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\AutoFailOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\FailExecutionOnOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\WarnAndIgnoreOpenActivitiesPolicy;
use ItPack\RunIt\Application\Scope\ExecutionScope;
use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\Instant;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use stdClass;
use Tests\Support\Fixture\RunItTestKit;
use Throwable;

/**
 * @group application
 * @group policy
 */
#[CoversClass(AutoCancelOpenActivitiesPolicy::class)]
#[CoversClass(AutoFailOpenActivitiesPolicy::class)]
#[CoversClass(FailExecutionOnOpenActivitiesPolicy::class)]
#[CoversClass(WarnAndIgnoreOpenActivitiesPolicy::class)]
final class OpenActivityPolicyTest extends Unit
{
    private RunItTestKit $kit;

    protected function setUp(): void
    {
        $this->kit = new RunItTestKit();
    }

    /**
     * @return iterable<string, array{0: ExecutionCompletionPolicyInterface, 1: ObservationSeverity, 2: string}>
     */
    public static function policyProvider(): iterable
    {
        yield 'auto-fail' => [new AutoFailOpenActivitiesPolicy(), ObservationSeverity::Critical, 'auto-resolved'];
        yield 'auto-cancel' => [new AutoCancelOpenActivitiesPolicy(), ObservationSeverity::Warning, 'auto-cancelled'];
        yield 'warn-and-ignore' => [new WarnAndIgnoreOpenActivitiesPolicy(), ObservationSeverity::Info, 'ignored'];
        yield 'fail-execution' => [
            new FailExecutionOnOpenActivitiesPolicy(),
            ObservationSeverity::Critical,
            'fail-execution policy applies',
        ];
    }

    private function openActivityObservation(ExecutionCompletionPolicyInterface $policy): ObservationInterface
    {
        $execution = Execution::create(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1),
        );
        $scope = $this->kit->buildScope($execution, ExecutionContext::empty());

        $activity = Activity::start(
            ActivityId::fromString('a1'),
            $execution->id(),
            ActivityDefinition::create(
                ActivityName::fromString('load'),
                ActivityKind::fromString('io')
            ),
            Instant::of(1),
        );
        $scope->trackOpenActivity($activity);
        $policy->resolveOpenActivities($scope, $scope->openActivities());

        $observations = [];
        foreach ($this->kit->artifacts() as $artifact) {
            if ($artifact instanceof ObservationInterface) {
                $observations[] = $artifact;
            }
        }

        return $observations[0];
    }

    #[DataProvider('policyProvider')]
    public function testPolicyRecordsDiagnosticObservationForOpenActivity(
        ExecutionCompletionPolicyInterface $policy,
        ObservationSeverity $severity,
        string $expectationFragment,
    ): void {
        $observation = $this->openActivityObservation($policy);

        self::assertSame($severity, $observation->severity());
        self::assertStringContainsString($expectationFragment, $observation->message());
    }

    /** @return iterable<string, array{bool, bool, bool, bool, ExecutionStatus}> */
    public static function outcomes(): iterable
    {
        yield 'selected with open activity' => [true, true, false, false, ExecutionStatus::Failed];
        yield 'selected without open activity' => [true, false, false, false, ExecutionStatus::Succeeded];
        yield 'selected with business failure' => [true, true, true, false, ExecutionStatus::Failed];
        yield 'selected with recorder failure' => [true, true, false, true, ExecutionStatus::Failed];
        yield 'default with open activity' => [false, true, false, false, ExecutionStatus::Succeeded];
        yield 'default with business failure' => [false, true, true, false, ExecutionStatus::Failed];
    }

    #[DataProvider('outcomes')]
    public function testPolicyPreservesBusinessOutcome(
        bool $selected,
        bool $open,
        bool $businessFails,
        bool $recorderFails,
        ExecutionStatus $expectedStatus,
    ): void {
        $allocator = new InMemorySequenceAllocator();
        if ($recorderFails) {
            $allocator = $this->createMock(SequenceAllocatorInterface::class);
            $delegate = new InMemorySequenceAllocator();
            $calls = 0;
            $allocator->method('allocate')->willReturnCallback(static function (ExecutionIdInterface $id) use (
                $delegate,
                &$calls
            ): ArtifactSequenceInterface {
                if ($calls++ > 0) {
                    throw new RuntimeException('recorder failure');
                }

                return $delegate->allocate($id);
            });
        }

        $reported = [];
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$reported): void {
                $reported[] = $failure;
            },
            sequenceAllocator: $allocator,
            completionPolicy: $selected ? new FailExecutionOnOpenActivitiesPolicy() : null,
        );
        $original = new RuntimeException('business failure');
        $value = new stdClass();
        $result = null;
        $caught = null;

        try {
            $result = $kit->runner->run(
                ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
                static function (ExecutionScopeInterface $scope) use (
                    $open,
                    $businessFails,
                    $original,
                    $value
                ): object {
                    self::assertInstanceOf(ExecutionScope::class, $scope);
                    if ($open) {
                        $scope->trackOpenActivity(Activity::start(
                            ActivityId::fromString('a1'),
                            $scope->execution()->id(),
                            ActivityDefinition::create(
                                ActivityName::fromString('load'),
                                ActivityKind::fromString('io'),
                            ),
                            Instant::of(1),
                        ));
                    }

                    if ($businessFails) {
                        throw $original;
                    }

                    return $value;
                },
            );
        } catch (Throwable $throwable) {
            $caught = $throwable;
        }

        self::assertSame($businessFails ? $original : null, $caught);
        self::assertSame($businessFails ? null : $value, $result);
        $observations = [];
        $terminal = [];
        foreach ($kit->artifacts() as $artifact) {
            if ($artifact instanceof ObservationInterface && $artifact->severity() === ObservationSeverity::Critical) {
                $observations[] = $artifact;
            } elseif ($artifact instanceof ExecutionInterface && $artifact->status()->isTerminal()) {
                $terminal[] = $artifact;
            }
        }

        self::assertCount($open && !$recorderFails ? 1 : 0, $observations);
        self::assertCount($recorderFails ? 1 : 0, $reported);
        self::assertCount(1, $terminal);
        self::assertInstanceOf(ExecutionInterface::class, $terminal[0]);
        self::assertSame(
            $expectedStatus,
            $terminal[0]->status(),
        );
        if ($selected && $open && !$businessFails) {
            $description = $terminal[0]->result()?->throwableDescription();
            self::assertNotNull($description);
            self::assertSame('open_activity_policy', $description->class());
            self::assertSame(
                'Execution failed because activities remained open at completion.',
                $description->message(),
            );
            self::assertNull($description->previous());
            self::assertNull($description->trace());
        }

        if ($businessFails) {
            self::assertSame(RuntimeException::class, $terminal[0]->result()?->throwableDescription()?->class());
        }

        self::assertNull($kit->storage->current());
    }
}
