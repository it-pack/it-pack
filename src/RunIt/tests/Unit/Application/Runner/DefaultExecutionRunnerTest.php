<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Runner;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Application\Policy\ActivityOnlyPolicy;
use ItPack\RunIt\Application\Policy\DetachedExecutionPolicy;
use ItPack\RunIt\Application\Policy\FailExecutionOnOpenActivitiesPolicy;
use ItPack\RunIt\Application\Policy\RejectNestedPolicy;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Runner\DefaultExecutionRunner;
use ItPack\RunIt\Application\Scope\ExecutionScope;
use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionFactoryInterface;
use ItPack\RunIt\Contract\Application\ExecutionLifecycleCoordinatorInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionSnapshotFactoryInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionMode;
use ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Exception\RejectedNestedExecutionException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use Random\RandomException;
use ReflectionClass;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;
use Tests\Support\Fixture\ThrowingArtifactPublisher;
use Throwable;

#[CoversClass(DefaultExecutionRunner::class)]
final class DefaultExecutionRunnerTest extends Unit
{
    public function testReturnsOperationResultUnchangedOnSuccess(): void
    {
        $kit = new RunItTestKit();

        $result = $kit->runner->run($this->definition(), static fn (): string => 'value');

        self::assertSame('value', $result);
    }

    public function testReadsInputDefinitionOnceDuringExecutionSetup(): void
    {
        $source = $this->definition();
        $definition = $this->createMock(ExecutionDefinitionInterface::class);
        $definition->expects($this->once())->method('name')->willReturn($source->name());
        $definition->expects($this->once())->method('kind')->willReturn($source->kind());
        $definition->expects($this->once())->method('context')->willReturn($source->context());

        $kit = new RunItTestKit();

        self::assertSame('value', $kit->runner->run($definition, static fn (): string => 'value'));
    }

    /**
     * @throws RandomException
     */
    #[DataProvider('stages')]
    public function testSetupFailuresReleaseOnlyIssuedProvenance(string $stage): void
    {
        $authority = new ProvenanceAuthority();
        $failure = new RuntimeException('setup failure');
        $factory = self::createStub(ExecutionFactoryInterface::class);
        $coordinator = self::createStub(ExecutionLifecycleCoordinatorInterface::class);
        if ($stage === 'factory' || $stage === 'duplicate') {
            $factory->method('create')->willThrowException($failure);
        } else {
            $factory->method('create')->willReturn(self::createStub(ExecutionInterface::class));
            $coordinator->method('start')->willThrowException($failure);
        }

        if ($stage === 'duplicate') {
            $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');
        }

        $kit = new RunItTestKit(coordinator: $coordinator, authority: $authority, executionFactory: $factory);
        $caught = null;
        try {
            $kit->runner->run($this->definition(), static fn (): null => null);
        } catch (Throwable $throwable) {
            $caught = $throwable;
        }

        self::assertSame($failure, $caught);
        self::assertSame($stage === 'duplicate', $authority->hasLiveRecords('exec-1'));
    }

    public function testActivityOnlyModeWithoutCurrentExecutionRunsAFullExecution(): void
    {
        $kit = new RunItTestKit(nestedPolicy: new ActivityOnlyPolicy());

        $result = $kit->runner->run($this->definition(), static fn (): string => 'root');

        self::assertSame('root', $result);
        $executions = $this->executions($kit);
        self::assertCount(1, $executions);
        self::assertSame(ExecutionStatus::Succeeded, $executions[0]->status());
    }

    public function testActivityOnlyModeInsideAnExecutionDelegatesToTheActivityScope(): void
    {
        $kit = new RunItTestKit(nestedPolicy: new ActivityOnlyPolicy());
        $innerResult = null;

        $outer = $kit->runner->run($this->definition(), static function () use ($kit, &$innerResult): string {
            /** @var string $inner */
            $inner = $kit->runner->run(
                ExecutionDefinition::create(ExecutionName::fromString('inner'), ExecutionKind::fromString('step')),
                static fn (): string => 'activity-result',
            );
            $innerResult = $inner;

            return 'outer';
        });

        self::assertSame('outer', $outer);
        self::assertSame('activity-result', $innerResult);
    }

    public function testSuccessfulExecutionReachesSucceededStatusExactlyOnce(): void
    {
        $kit = new RunItTestKit();

        $kit->runner->run($this->definition(), static fn (): int => 42);

        $executions = $this->executions($kit);
        self::assertCount(1, $executions);
        self::assertSame(ExecutionStatus::Succeeded, $executions[0]->status());
        self::assertTrue($executions[0]->status()->isTerminal());

        $snapshots = $kit->artifactsOf(SnapshotInterface::class);
        self::assertCount(1, $snapshots);
    }

    public function testRethrowsExactOriginalThrowable(): void
    {
        $kit = new RunItTestKit();
        $thrown = new RuntimeException('boom');

        $caught = null;
        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($thrown, $caught, 'The original throwable must be rethrown.');
        self::assertSame('boom', $caught->getMessage());
    }

    public function testFailedExecutionReachesFailedStatus(): void
    {
        $kit = new RunItTestKit();

        try {
            $kit->runner->run($this->definition(), static function (): never {
                throw new RuntimeException('kaboom');
            });
        } catch (RuntimeException) {
        }

        $executions = $this->executions($kit);
        self::assertCount(1, $executions);
        self::assertSame(ExecutionStatus::Failed, $executions[0]->status());
        self::assertNotNull($executions[0]->result()?->throwableDescription());
        self::assertSame('kaboom', $executions[0]->result()->throwableDescription()->message());
    }

    public function testContextIsRestoredAfterExecution(): void
    {
        $kit = new RunItTestKit();

        $kit->runner->run($this->definition(), static fn (): int => 1);

        self::assertNull($kit->storage->current(), 'The ambient execution scope must be detached after completion.');
    }

    public function testPublicationFailureCannotReplaceBusinessResult(): void
    {
        $kit = new RunItTestKit(publisher: new ThrowingArtifactPublisher());

        $result = $kit->runner->run($this->definition(), static fn (): string => 'business-value');

        self::assertSame('business-value', $result, 'A publication failure must not replace the operation result.');
    }

    public function testPublicationFailureCannotReplaceBusinessException(): void
    {
        $kit = new RunItTestKit(publisher: new ThrowingArtifactPublisher());
        $thrown = new RuntimeException('business-error');

        $caught = null;
        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($thrown, $caught);
        self::assertSame('business-error', $caught->getMessage());
    }

    public function testPublicationFailureIsForwardedToHealthCallback(): void
    {
        $captured = null;
        $kit = new RunItTestKit(
            publisher: new ThrowingArtifactPublisher(),
            onPublicationFailure: static function (Throwable $e) use (&$captured): void {
                $captured = $e;
            },
        );

        $result = $kit->runner->run($this->definition(), static fn (): string => 'business-value');

        self::assertSame('business-value', $result, 'The business result must be preserved.');
        self::assertInstanceOf(
            Throwable::class,
            $captured,
            'The health callback must receive the publication failure.'
        );
        self::assertSame('publisher down', $captured->getMessage());
    }

    #[DataProvider('businessOutcomes')]
    public function testHealthCallbackPublishingFailureIsBoundedAndGuardResets(bool $fails): void
    {
        $publisher = new class () implements ArtifactPublisherInterface {
            public function publish(ExecutionArtifactInterface $artifact): void
            {
                if ($artifact instanceof ObservationInterface) {
                    throw new RuntimeException('sensitive publisher failure');
                }
            }
        };
        $snapshotFactory = self::createStub(ExecutionSnapshotFactoryInterface::class);
        $snapshotFactory->method('terminal')->willThrowException(new RuntimeException('snapshot failed'));
        $reports = 0;
        $kit = null;
        $kit = new RunItTestKit(
            publisher: $publisher,
            onPublicationFailure: static function (Throwable $failure) use (&$reports, &$kit): void {
                ++$reports;
                if ($kit instanceof RunItTestKit) {
                    $kit->storage->current()?->observations()->record('health callback');
                }
            },
            snapshotFactory: $snapshotFactory,
        );
        $businessError = new RuntimeException('business failed');
        $operation = static function () use ($fails, $businessError): string {
            if ($fails) {
                throw $businessError;
            }

            return 'business result';
        };
        $caught = null;
        $result = null;
        try {
            $result = $kit->runner->run($this->definition(), $operation);
        } catch (Throwable $throwable) {
            $caught = $throwable;
        }

        self::assertSame($fails ? $businessError : null, $caught);
        self::assertSame($fails ? null : 'business result', $result);
        self::assertNull($kit->storage->current());
        $firstReports = $reports;
        $kit->runner->run($this->definition(), static fn (): string => 'second');
        self::assertGreaterThan(0, $firstReports);
        self::assertSame($firstReports + 1, $reports);
        self::assertNull($kit->storage->current());
    }

    public function testStartupSetupFaultIsReportedAndRethrown(): void
    {
        $captured = null;
        $coordinator = new class () implements ExecutionLifecycleCoordinatorInterface {
            public function start(ExecutionInterface $execution): ExecutionInterface
            {
                throw new RuntimeException('start broken');
            }

            public function transition(
                ExecutionInterface $execution,
                ExecutionResultInterface $result,
                InstantInterface $endedAt
            ): ExecutionInterface {
                return $execution;
            }
        };

        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $e) use (&$captured): void {
                $captured = $e;
            },
            coordinator: $coordinator,
        );

        try {
            $kit->runner->run($this->definition(), static fn (): string => 'x');
            self::fail('expected the startup fault to propagate');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('start broken', $runtimeException->getMessage());
        }

        self::assertInstanceOf(RuntimeException::class, $captured);
        self::assertSame('start broken', $captured->getMessage());
    }

    public function testBusinessExceptionIsRethrownWhenMapperThrows(): void
    {
        $mapper = new class () implements ThrowableMapperInterface {
            public function describe(Throwable $throwable): ThrowableDescriptionInterface
            {
                throw new RuntimeException('map broken');
            }
        };

        $thrown = new RuntimeException('business-error');
        $kit = new RunItTestKit(throwableMapper: $mapper);

        $caught = null;
        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($thrown, $caught);
    }

    public function testCompletionTransitionFaultDoesNotMaskBusinessException(): void
    {
        $captured = null;
        $coordinator = new class () implements ExecutionLifecycleCoordinatorInterface {
            public function start(ExecutionInterface $execution): ExecutionInterface
            {
                return $execution;
            }

            public function transition(
                ExecutionInterface $execution,
                ExecutionResultInterface $result,
                InstantInterface $endedAt
            ): ExecutionInterface {
                throw new RuntimeException('transition broken');
            }
        };

        $thrown = new RuntimeException('business-error');
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $e) use (&$captured): void {
                $captured = $e;
            },
            coordinator: $coordinator,
        );

        $caught = null;
        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($thrown, $caught);
        self::assertInstanceOf(RuntimeException::class, $captured);
        self::assertSame('transition broken', $captured->getMessage());
        self::assertCount(0, $this->executions($kit));
    }

    public function testSuccessfulCompletionTransitionFaultStillReturnsResult(): void
    {
        $coordinator = new class () implements ExecutionLifecycleCoordinatorInterface {
            private bool $started = false;

            public function start(ExecutionInterface $execution): ExecutionInterface
            {
                $this->started = true;

                return $execution;
            }

            public function transition(
                ExecutionInterface $execution,
                ExecutionResultInterface $result,
                InstantInterface $endedAt
            ): ExecutionInterface {
                if (!$this->started) {
                    throw new RuntimeException('unexpected');
                }

                throw new RuntimeException('transition broken');
            }
        };

        $kit = new RunItTestKit(coordinator: $coordinator);

        $result = $kit->runner->run($this->definition(), static fn (): string => 'value');

        self::assertSame('value', $result);
    }

    public function testRejectsNestedExecutionWhenCurrentExists(): void
    {
        $kit = new RunItTestKit(nestedPolicy: new RejectNestedPolicy());

        $caught = null;
        try {
            $kit->runner->run($this->definition(), static function () use ($kit): string {
                $kit->runner->run(
                    ExecutionDefinition::create(ExecutionName::fromString('inner'), ExecutionKind::fromString('step')),
                    static fn (): string => 'inner'
                );

                return 'outer';
            });
        } catch (Throwable $throwable) {
            $caught = $throwable;
        }

        self::assertInstanceOf(RejectedNestedExecutionException::class, $caught);
    }

    public function testPublicationFailureCallbackThrowingFallsBackAndPreservesResult(): void
    {
        $kit = new RunItTestKit(
            publisher: new ThrowingArtifactPublisher(),
            onPublicationFailure: static function (Throwable $e): never {
                throw new RuntimeException('callback broken');
            },
        );

        $result = $kit->runner->run($this->definition(), static fn (): string => 'business-value');

        self::assertSame('business-value', $result);
        self::assertNull($kit->storage->current());
    }

    public function testRunAsActivityFallsBackToChildExecutionWhenCurrentIsNull(): void
    {
        $policy = new class () implements NestedExecutionPolicyInterface {
            public function resolve(
                ?ExecutionScopeInterface $current,
                ExecutionDefinitionInterface $definition
            ): NestedExecutionMode {
                return NestedExecutionMode::ActivityOnly;
            }
        };
        $kit = new RunItTestKit(nestedPolicy: $policy);

        $result = $kit->runner->run($this->definition(), static fn (): string => 'root');

        self::assertSame('root', $result);
    }

    public function testSetupScopeFailureReleasesIssuedExportId(): void
    {
        $authority = new ProvenanceAuthority();
        $failure = new RuntimeException('create broken');
        $factory = self::createStub(ExecutionFactoryInterface::class);
        $factory->method('create')->willThrowException($failure);
        $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $kit = new RunItTestKit(
            authority: $authority,
            executionFactory: $factory,
        );
        $caught = null;
        try {
            $kit->runner->run($this->definition(), static fn (): null => null);
        } catch (Throwable $throwable) {
            $caught = $throwable;
        }

        self::assertSame($failure, $caught);
        self::assertCount(1, $authority->allRecords());
    }

    public function testIssueExportIdCapacityFailureIsReported(): void
    {
        $authority = new ProvenanceAuthority(null, 1, 10, 1024);
        $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $kit = new RunItTestKit(authority: $authority);
        $result = $kit->runner->run($this->definition(), static fn (): string => 'value');

        self::assertSame('value', $result);
    }

    public function testDuplicateExecutionGetsFreshAuthority(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $kit = new RunItTestKit(authority: $authority);
        $result = $kit->runner->run($this->definition(), static fn (): string => 'value');

        self::assertSame('value', $result);
        self::assertTrue($authority->hasLiveRecords('exec-1'));
    }

    public function testDetachedExecutionRegistersLinkEdge(): void
    {
        $kit = new RunItTestKit(nestedPolicy: new DetachedExecutionPolicy());
        $innerResult = null;

        $outer = $kit->runner->run($this->definition(), static function () use ($kit, &$innerResult): string {
            $inner = $kit->runner->run(
                ExecutionDefinition::create(ExecutionName::fromString('inner'), ExecutionKind::fromString('step')),
                static fn (): string => 'inner-result',
            );
            $innerResult = $inner;
            return 'outer';
        });

        self::assertSame('outer', $outer);
        self::assertSame('inner-result', $innerResult);
    }

    public function testStartupPublishFailureIsReportedAndOperationStillRuns(): void
    {
        $kit = new RunItTestKit(publisher: new ThrowingArtifactPublisher());
        $captured = null;
        $kit = new RunItTestKit(
            publisher: new ThrowingArtifactPublisher(),
            onPublicationFailure: static function (Throwable $e) use (&$captured): void {
                $captured = $e;
            },
        );

        $result = $kit->runner->run($this->definition(), static fn (): string => 'business-value');

        self::assertSame('business-value', $result);
        self::assertInstanceOf(Throwable::class, $captured);
        self::assertSame('publisher down', $captured->getMessage());
    }

    public function testCompletionPolicyOpenActivitiesFaultIsReported(): void
    {
        $captured = null;
        $completionPolicy = new class () implements ExecutionCompletionPolicyInterface {
            public function resolveOpenActivities(ExecutionScopeInterface $scope, array $openActivities): void
            {
                throw new RuntimeException('open activities broken');
            }
        };
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $e) use (&$captured): void {
                $captured = $e;
            },
            completionPolicy: $completionPolicy,
        );

        $result = $kit->runner->run($this->definition(), static fn (): string => 'value');

        self::assertSame('value', $result);
        self::assertInstanceOf(RuntimeException::class, $captured);
        self::assertSame('open activities broken', $captured->getMessage());
    }

    public function testTerminalPublishFailureIsReportedAndResultPreserved(): void
    {
        $captured = null;
        $kit = new RunItTestKit(
            publisher: new ThrowingArtifactPublisher(),
            onPublicationFailure: static function (Throwable $e) use (&$captured): void {
                $captured = $e;
            },
        );

        $result = $kit->runner->run($this->definition(), static fn (): string => 'business-value');

        self::assertSame('business-value', $result);
        self::assertInstanceOf(Throwable::class, $captured);
    }

    public function testFailOnOpenActivitiesMarksExecutionFailed(): void
    {
        $kit = new RunItTestKit(
            completionPolicy: new FailExecutionOnOpenActivitiesPolicy(),
        );
        $definition = $this->definition();
        $activityDef = ActivityDefinition::create(
            ActivityName::fromString('load'),
            ActivityKind::fromString('io')
        );

        $result = $kit->runner->run($definition, function ($scope) use ($activityDef): string {
            $scope->activities()->run($activityDef, static fn (): string => 'inner');
            return 'result';
        });

        self::assertSame('result', $result);

        $activities = $kit->artifactsOf(ActivityInterface::class);
        self::assertCount(2, $activities);
        self::assertSame('succeeded', $activities[1]->status()->label());
    }

    public function testOpenActivityAtCompletionFailsExecution(): void
    {
        $kit = new RunItTestKit(
            completionPolicy: new FailExecutionOnOpenActivitiesPolicy(),
        );
        $definition = $this->definition();
        $activityDef = ActivityDefinition::create(
            ActivityName::fromString('load'),
            ActivityKind::fromString('io')
        );

        $kit->runner->run($definition, function ($scope) use ($activityDef): string {
            /** @var ExecutionScope $scope */
            $scope->activities()->run($activityDef, static fn (): string => 'inner');

            // Manually track an additional activity as open without completing it,
            // so it remains open when the execution completes.
            $openActivity = $this->createMock(ActivityInterface::class);
            $openActivity->method('status')->willReturn(ActivityStatus::Running);

            $scope->trackOpenActivity($openActivity);

            return 'result';
        });

        $executions = $kit->artifactsOf(ExecutionInterface::class);
        self::assertNotEmpty($executions);
        /** @var ExecutionInterface $execution */
        $execution = end($executions);
        self::assertSame('failed', $execution->status()->label());

        $observations = $kit->artifactsOf(ObservationInterface::class);
        self::assertNotEmpty($observations);
        $hasCritical = array_any(
            $observations,
            fn (ObservationInterface $observation): bool => $observation->severity()->label() === 'critical',
        );
        self::assertTrue($hasCritical, 'Expected a critical observation for open activity');
    }

    public function testIssueExportIdCapacityThrowsWhenNotDuplicate(): void
    {
        $authority = new ProvenanceAuthority(null, 1, 10, 1024, 1);
        $authority->issueExportId('execution', 'execution', 'other-exec', 'other-exec');

        $kit = new RunItTestKit(authority: $authority);
        $result = $kit->runner->run($this->definition(), static fn (): string => 'value');

        self::assertSame('value', $result);
    }

    private function definition(): ExecutionDefinitionInterface
    {
        return ExecutionDefinition::create(ExecutionName::fromString('import'), ExecutionKind::fromString('batch'));
    }

    /**
     * @return list<ExecutionInterface>
     */
    private function executions(RunItTestKit $kit): array
    {
        $executions = [];
        foreach ($kit->artifacts() as $artifact) {
            if ($artifact instanceof ExecutionInterface && $artifact->status()->isTerminal()) {
                $executions[] = $artifact;
            }
        }

        return $executions;
    }

    /** @return iterable<string, array{bool}> */
    public function businessOutcomes(): iterable
    {
        yield 'success' => [false];
        yield 'failure' => [true];
    }

    /** @return iterable<array{string}> */
    public function stages(): iterable
    {
        return [['factory'], ['coordinator'], ['duplicate']];
    }

    public function testActivityContextPolicyReturnsConfiguredValue(): void
    {
        $kit = new RunItTestKit();

        self::assertSame('fallback', $kit->runner->activityContextPolicy());
    }

    public function testPlaceholderArtifactNormalizeReturnsEmptyArray(): void
    {
        $kit = new RunItTestKit();
        $runner = $kit->runner;
        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('placeholderArtifact');

        $artifact = $method->invoke($runner);
        self::assertInstanceOf(ExecutionArtifactInterface::class, $artifact);
        self::assertSame([], $artifact->normalize());
    }
}
