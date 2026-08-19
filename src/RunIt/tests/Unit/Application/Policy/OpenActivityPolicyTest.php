<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Policy;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Policy\AutoCancelOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\AutoFailOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\FailExecutionOnOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\WarnAndIgnoreOpenActivitiesPolicy;
use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\Instant;
use Tests\Support\Fixture\RunItTestKit;

/**
 * @group application
 * @group policy
 */
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
    public function policyProvider(): iterable
    {
        yield 'auto-fail' => [new AutoFailOpenActivitiesPolicy(), ObservationSeverity::Warning, 'auto-resolved'];
        yield 'auto-cancel' => [new AutoCancelOpenActivitiesPolicy(), ObservationSeverity::Warning, 'auto-cancelled'];
        yield 'warn-and-ignore' => [new WarnAndIgnoreOpenActivitiesPolicy(), ObservationSeverity::Info, 'ignored'];
        yield 'fail-execution' => [new FailExecutionOnOpenActivitiesPolicy(), ObservationSeverity::Critical, 'flagged as failed'];
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
            \ItPack\RunIt\Domain\Activity\ActivityDefinition::create(ActivityName::fromString('load'), ActivityKind::fromString('io')),
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

    /**
     * @dataProvider policyProvider
     */
    public function testPolicyRecordsDiagnosticObservationForOpenActivity(
        ExecutionCompletionPolicyInterface $policy,
        ObservationSeverity $severity,
        string $expectationFragment,
    ): void {
        $observation = $this->openActivityObservation($policy);

        self::assertSame($severity, $observation->severity());
        self::assertStringContainsString($expectationFragment, $observation->message());
    }
}
