<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Runner;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Policy\RejectNestedPolicy;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Domain\Exception\ActivityDepthExceededException;
use ItPack\RunIt\Domain\Exception\RejectedNestedExecutionException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
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
                    \ItPack\RunIt\Domain\Activity\ActivityDefinition::create(
                        \ItPack\RunIt\Domain\Activity\ActivityName::fromString('outer'),
                        \ItPack\RunIt\Domain\Activity\ActivityKind::fromString('task'),
                    ),
                    static function () use ($scope): void {
                        $scope->activities()->run(
                            \ItPack\RunIt\Domain\Activity\ActivityDefinition::create(
                                \ItPack\RunIt\Domain\Activity\ActivityName::fromString('inner'),
                                \ItPack\RunIt\Domain\Activity\ActivityKind::fromString('task'),
                            ),
                            static fn (): string => 'never',
                        );
                    },
                );
            });
            self::fail('nesting beyond the depth limit must be rejected');
        } catch (ActivityDepthExceededException $exception) {
            self::assertStringContainsString('must not exceed 1, got 2', $exception->getMessage());
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
        } catch (RejectedNestedExecutionException $exception) {
            self::assertStringContainsString('already active', $exception->getMessage());
        }
    }

    public function testSuccessfulNestingReachesActivityScopeDelegations(): void
    {
        $kit = new RunItTestKit(limits: new SecurityLimits(maxActivityDepth: 5));
        $definition = $this->definition();

        $result = $kit->runner->run($definition, static function (ExecutionScopeInterface $scope): string {
            return $scope->activities()->run(
                \ItPack\RunIt\Domain\Activity\ActivityDefinition::create(
                    \ItPack\RunIt\Domain\Activity\ActivityName::fromString('outer'),
                    \ItPack\RunIt\Domain\Activity\ActivityKind::fromString('task'),
                ),
                static function (ExecutionScopeInterface $innerScope): string {
                    // Delegates through the activity scope back to the execution scope.
                    $innerScope->snapshots()->record(
                        \ItPack\RunIt\Domain\Snapshot\SnapshotType::fromString('stage.snapshot'),
                        ['stage' => 'nested'],
                    );

                    return 'nested-ok';
                },
            );
        });

        self::assertSame('nested-ok', $result);
    }

    private function definition(): ExecutionDefinitionInterface
    {
        return ExecutionDefinition::create(ExecutionName::fromString('boundary'), ExecutionKind::fromString('task'));
    }
}
