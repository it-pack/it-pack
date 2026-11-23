<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Runner;

use Codeception\Test\Unit;
use Fiber;
use ItPack\RunIt\Application\Propagation\StackActivityContextStorage;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Runner\DefaultActivityRunner;
use ItPack\RunIt\Application\Scope\ExecutionScope;
use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Port\ActivityContextStorageInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Factory\StringIdentifierFactory;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;
use Tests\Support\Fixture\SequenceIdentifierGenerator;

#[CoversClass(DefaultActivityRunner::class)]
final class DefaultActivityRunnerFailureTest extends Unit
{
    public function testStartPublicationFailurePreservesResultAndCleansUp(): void
    {
        $this->exerciseFailure('start');
    }

    public function testTerminalPublicationFailurePreservesResultAndCleansUp(): void
    {
        $this->exerciseFailure('terminal');
    }

    public function testDetachFailurePreservesResultAndClearsOpenActivity(): void
    {
        $this->exerciseFailure('detach');
    }

    public function testBusinessExceptionSurvivesTerminalPublicationAndDetachFailures(): void
    {
        $this->exerciseFailure('business');
    }

    public function testAttachFailureAbortsBusinessCallbackAndClearsOpenActivity(): void
    {
        $this->exerciseFailure('attach');
    }

    public function testOptInAttachFailurePreservesBusinessResult(): void
    {
        $this->exerciseFailure('attach-open');
    }

    public function testOptInAttachFailurePreservesBusinessException(): void
    {
        $this->exerciseFailure('attach-business');
    }

    public function testRunWithoutActivityStorageSetsCurrentActivityId(): void
    {
        $kit = new RunItTestKit();
        $execution = $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            static fn ($scope) => $scope->execution()
        );

        $context = $this->createMock(ExecutionContextInterface::class);
        $scope = $kit->buildScope($execution, $context);

        $priorActivityId = ActivityId::fromString('prior-act');
        $scope->setCurrentActivityId($priorActivityId);

        $runner = new DefaultActivityRunner(
            $scope,
            $scope->execution()->executionId(),
            $kit->allocator,
            $kit->clock,
            $kit->publisher,
            new DefaultThrowableMapper(),
            new StringIdentifierFactory(
                ActivityId::fromString(...),
                new SequenceIdentifierGenerator('test-act'),
            ),
        );

        $activity = ActivityDefinition::create(
            ActivityName::fromString('activity'),
            ActivityKind::fromString('task'),
        );

        $result = $runner->run($activity, static function () use ($scope): string {
            self::assertSame('test-act-1', $scope->currentActivityId()?->toString());
            return 'result';
        });

        self::assertSame('result', $result);
        self::assertSame($priorActivityId, $scope->currentActivityId());
    }

    public function testFailedNestedAttachSuppressesOuterAndOverlappingRecordingsUntilRestored(): void
    {
        foreach (['fallback', 'drop', 'throw'] as $policy) {
            $backing = new StackActivityContextStorage();
            $storage = $this->createMock(ActivityContextStorageInterface::class);
            $attachments = 0;
            $storage->method('attach')->willReturnCallback(
                static function (ActivityScopeInterface $scope) use ($backing, &$attachments): ContextTokenInterface {
                    if (++$attachments >= 2) {
                        throw new RuntimeException('attach failed');
                    }

                    return $backing->attach($scope);
                },
            );
            $storage->method('current')->willReturnCallback($backing->current(...));
            $storage->method('detach')->willReturnCallback($backing->detach(...));
            $authority = new ProvenanceAuthority();
            $kit = new RunItTestKit(
                authority: $authority,
                activityContextStorage: $storage,
                activityContextPolicy: $policy,
            );
            $execution = ExecutionDefinition::create(
                ExecutionName::fromString('op'),
                ExecutionKind::fromString('task'),
            );
            $definition = ActivityDefinition::create(
                ActivityName::fromString('activity'),
                ActivityKind::fromString('task'),
            );
            $kit->runner->run($execution, function (ExecutionScopeInterface $scope) use (
                $kit,
                $storage,
                $authority,
                $policy,
                $definition,
            ): void {
                self::assertInstanceOf(ExecutionScope::class, $scope);
                $scope->activities()->run($definition, function (ActivityScopeInterface $outer) use (
                    $scope,
                    $kit,
                    $storage,
                    $authority,
                    $policy,
                    $definition,
                ): void {
                    self::assertNotNull($scope->observations()->record('before')->activityId());
                    $runner = new DefaultActivityRunner(
                        $scope,
                        $scope->execution()->executionId(),
                        $kit->allocator,
                        $kit->clock,
                        $kit->publisher,
                        new DefaultThrowableMapper(),
                        new StringIdentifierFactory(
                            ActivityId::fromString(...),
                            new SequenceIdentifierGenerator('inner'),
                        ),
                        authority: $authority,
                        activityStorage: $storage,
                        runOnActivityContextAttachFailure: true,
                    );
                    $result = $runner->run($definition, static function () use (
                        $scope,
                        $kit,
                        $policy,
                        $runner,
                        $definition,
                    ): string {
                        $runner->run($definition, static fn (): string => 'nested');
                        // An overlapping caller sharing this execution is conservatively suppressed too.
                        $fiber = new Fiber(static function () use ($scope, $kit, $policy): void {
                            $before = count($kit->artifacts());
                            try {
                                $observation = $scope->observations()->record('during');
                                self::assertNotSame('throw', $policy);
                                self::assertNull($observation->activityId());
                                self::assertCount($before + ($policy === 'fallback' ? 1 : 0), $kit->artifacts());
                            } catch (RuntimeException) {
                                self::assertSame('throw', $policy);
                                self::assertCount($before, $kit->artifacts());
                            }
                        });
                        $fiber->start();
                        return 'preserved';
                    });
                    self::assertSame('preserved', $result);
                    $businessFailure = new RuntimeException('business failure');
                    $caught = null;
                    try {
                        $runner->run($definition, static function () use ($businessFailure): never {
                            throw $businessFailure;
                        });
                    } catch (RuntimeException $runtimeException) {
                        $caught = $runtimeException;
                    }

                    self::assertSame($businessFailure, $caught);
                    self::assertSame(
                        $outer->activity()->activityId()?->toString(),
                        $scope->observations()->record('after')->activityId()?->toString()
                    );
                    self::assertCount(1, $scope->openActivities());
                });
                self::assertSame([], $scope->openActivities());
                self::assertNull($storage->current());
            });
        }
    }

    private function exerciseFailure(string $scenario): void
    {
        $kit = new RunItTestKit();
        $definition = ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task'));
        $kit->runner->run($definition, function (ExecutionScopeInterface $scope) use ($kit, $scenario): void {
            self::assertInstanceOf(ExecutionScope::class, $scope);
            $storage = $this->createMock(ActivityContextStorageInterface::class);
            $token = $this->createMock(ContextTokenInterface::class);
            $failure = new RuntimeException('instrumentation failure');
            $businessFailure = new RuntimeException('business failure');
            $published = 0;
            $ran = false;
            $reports = [];
            $publisher = $this->createMock(ArtifactPublisherInterface::class);
            $publisher->method('publish')->willReturnCallback(
                static function (ExecutionArtifactInterface $artifact) use (&$published, $scenario, $failure): void {
                    ++$published;
                    if (($scenario === 'start' && $published === 1)
                        || (in_array($scenario, ['terminal', 'business'], true) && $published === 2)) {
                        throw $failure;
                    }
                },
            );
            $attach = $storage->expects(self::once())->method('attach');
            if (str_starts_with($scenario, 'attach')) {
                $attach->willThrowException($failure);
                $storage->expects(self::never())->method('detach');
            } else {
                $attach->willReturn($token);
                $detach = $storage->expects(self::once())->method('detach')->with(self::identicalTo($token));
                if (in_array($scenario, ['detach', 'business'], true)) {
                    $detach->willThrowException($failure);
                }
            }

            $runner = new DefaultActivityRunner(
                $scope,
                $scope->execution()->executionId(),
                $kit->allocator,
                $kit->clock,
                $publisher,
                new DefaultThrowableMapper(),
                new StringIdentifierFactory(
                    ActivityId::fromString(...),
                    new SequenceIdentifierGenerator('failure-act'),
                ),
                activityStorage: $storage,
                onActivityContextFailure: static function (string $reason) use (&$reports): void {
                    $reports[] = $reason;
                },
                runOnActivityContextAttachFailure: $scenario !== 'attach' && str_starts_with($scenario, 'attach'),
            );
            $activity = ActivityDefinition::create(
                ActivityName::fromString('activity'),
                ActivityKind::fromString('task'),
            );
            $caught = null;
            $result = null;
            try {
                $result = $runner->run($activity, static function (ActivityScopeInterface $active) use (
                    &$ran,
                    $scenario,
                    $businessFailure,
                    $scope,
                ): string {
                    $ran = true;
                    self::assertSame($scope, $active->parentScope());
                    self::assertCount(1, $scope->openActivities());
                    if (in_array($scenario, ['business', 'attach-business'], true)) {
                        throw $businessFailure;
                    }

                    return 'business result';
                });
            } catch (RuntimeException $runtimeException) {
                $caught = $runtimeException;
            }

            self::assertSame([], $scope->openActivities());
            self::assertNull($scope->currentActivityId());
            self::assertSame($scenario !== 'attach', $ran);
            self::assertSame($scenario === 'attach' ? 0 : 2, $published);
            self::assertSame(match ($scenario) {
                'attach' => $failure,
                'business', 'attach-business' => $businessFailure,
                default => null,
            }, $caught);
            self::assertSame(
                in_array($scenario, ['attach', 'business', 'attach-business'], true) ? null : 'business result',
                $result,
            );
            self::assertSame(match ($scenario) {
                'start' => ['activity_start_publication_failed'],
                'terminal' => ['activity_terminal_publication_failed'],
                'detach' => ['activity_context_detach_failed'],
                'business' => ['activity_terminal_publication_failed', 'activity_context_detach_failed'],
                'attach', 'attach-open', 'attach-business' => ['activity_context_attach_failed'],
                default => throw new RuntimeException('Unknown test scenario.'),
            }, $reports);
        });
    }
}
