<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Runner;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Policy\RejectNestedPolicy;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Exception\ActivityDepthExceededException;
use ItPack\RunIt\Domain\Exception\RejectedNestedExecutionException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use Tests\Support\Fixture\RunItTestKit;

/**
 * Boundary behavior of the runner: activity-depth bounds and policy-rejected
 * nesting surface as their domain exceptions without corrupting lifecycle state.
 *
 * @group application
 * @group resilience
 */
final class NestedBoundariesTest extends Unit
{
    public function testNestingBeyondTheDepthLimitRaisesTheDepthException(): void
    {
        $kit = new RunItTestKit(limits: new SecurityLimits(maxActivityDepth: 1));
        $definition = $this->definition();

        try {
            $kit->runner->run($definition, static function (ExecutionScopeInterface $scope): void {
                $scope->activities()->run(
                    ActivityDefinition::create(
                        ActivityName::fromString('outer'),
                        ActivityKind::fromString('task'),
                    ),
                    static function () use ($scope): void {
                        $scope->activities()->run(
                            ActivityDefinition::create(
                                ActivityName::fromString('inner'),
                                ActivityKind::fromString('task'),
                            ),
                            static fn (): string => 'never',
                        );
                    },
                );
            });
            self::fail('nesting beyond the depth limit must be rejected');
        } catch (ActivityDepthExceededException $activityDepthExceededException) {
            self::assertStringContainsString('must not exceed 1, got 2', $activityDepthExceededException->getMessage());
        }
    }

    public function testRejectNestedPolicySurfacesTheRejectionException(): void
    {
        $kit = new RunItTestKit(nestedPolicy: new RejectNestedPolicy());
        $definition = $this->definition();

        try {
            $kit->runner->run($definition, static function () use ($kit, $definition): void {
                $kit->runner->run($definition, static fn (): string => 'nested');
            });
            self::fail('a rejected nested execution must raise the rejection exception');
        } catch (RejectedNestedExecutionException $rejectedNestedExecutionException) {
            self::assertStringContainsString('already active', $rejectedNestedExecutionException->getMessage());
        }
    }

    public function testSuccessfulNestingReachesActivityScopeDelegations(): void
    {
        $kit = new RunItTestKit(limits: new SecurityLimits(maxActivityDepth: 5));
        $definition = $this->definition();

        $result = $kit->runner->run($definition, static fn (ExecutionScopeInterface $scope): string => $scope->activities()->run(
            ActivityDefinition::create(
                ActivityName::fromString('outer'),
                ActivityKind::fromString('task'),
            ),
            static function (ExecutionScopeInterface $innerScope): string {
                // Delegates through the activity scope back to the execution scope.
                $innerScope->snapshots()->record(
                    SnapshotType::fromString('stage.snapshot'),
                    ['stage' => 'nested'],
                );

                return 'nested-ok';
            },
        ));

        self::assertSame('nested-ok', $result);
    }

    private function definition(): ExecutionDefinitionInterface
    {
        return ExecutionDefinition::create(ExecutionName::fromString('boundary'), ExecutionKind::fromString('task'));
    }
}
