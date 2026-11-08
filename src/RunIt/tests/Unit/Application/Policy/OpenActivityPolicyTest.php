<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Policy;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Application\Policy\AutoCancelOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\AutoFailOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\FailExecutionOnOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\WarnAndIgnoreOpenActivitiesPolicy;
use ItPack\RunIt\Application\Scope\ExecutionScope;
use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
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
        yield 'auto-fail' => [new AutoFailOpenActivitiesPolicy(), ObservationSeverity::Warning, 'auto-resolved'];
        yield 'auto-cancel' => [new AutoCancelOpenActivitiesPolicy(), ObservationSeverity::Warning, 'auto-cancelled'];
        yield 'warn-and-ignore' => [new WarnAndIgnoreOpenActivitiesPolicy(), ObservationSeverity::Info, 'ignored'];
        yield 'fail-execution' => [
            new FailExecutionOnOpenActivitiesPolicy(),
            ObservationSeverity::Critical,
            'flagged as failed',
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

    /** Characterizes the policy's diagnostic without implying it changes the execution outcome. */
    public function testFailExecutionPolicyPreservesOperationOutcomeWithOpenActivity(): void
    {
        foreach ([false, true] as $businessFails) {
            $kit = new RunItTestKit(completionPolicy: new FailExecutionOnOpenActivitiesPolicy());
            $original = new RuntimeException('business failure');
            $value = new stdClass();
            $result = null;
            $caught = null;

            try {
                $result = $kit->runner->run(
                    ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
                    static function (ExecutionScopeInterface $scope) use ($businessFails, $original, $value): object {
                        self::assertInstanceOf(ExecutionScope::class, $scope);
                        $scope->trackOpenActivity(Activity::start(
                            ActivityId::fromString('a1'),
                            $scope->execution()->id(),
                            ActivityDefinition::create(
                                ActivityName::fromString('load'),
                                ActivityKind::fromString('io'),
                            ),
                            Instant::of(1),
                        ));
                        if ($businessFails) {
                            throw $original;
                        }

                        return $value;
                    },
                );
            } catch (Throwable $failure) {
                $caught = $failure;
            }

            self::assertSame($businessFails ? $original : null, $caught);
            self::assertSame($businessFails ? null : $value, $result);
            $observations = array_values(array_filter(
                $kit->artifacts(),
                static fn (ExecutionArtifactInterface $artifact): bool => $artifact instanceof ObservationInterface
                    && $artifact->severity() === ObservationSeverity::Critical,
            ));
            self::assertCount(1, $observations);
            self::assertInstanceOf(ObservationInterface::class, $observations[0]);
            self::assertSame(
                'Activity "load" was still open at execution completion; the execution is flagged as failed.',
                $observations[0]->message(),
            );
            $terminal = array_values(array_filter(
                $kit->artifacts(),
                static fn (ExecutionArtifactInterface $artifact): bool => $artifact instanceof ExecutionInterface
                    && $artifact->status()->isTerminal(),
            ));
            self::assertCount(1, $terminal);
            self::assertInstanceOf(ExecutionInterface::class, $terminal[0]);
            self::assertSame(
                $businessFails ? ExecutionStatus::Failed : ExecutionStatus::Succeeded,
                $terminal[0]->status(),
            );
            self::assertNull($kit->storage->current());
        }
    }
}
