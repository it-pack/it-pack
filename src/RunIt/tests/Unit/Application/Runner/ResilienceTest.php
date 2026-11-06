<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Runner;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Lifecycle\ExecutionLifecycleCoordinator;
use ItPack\RunIt\Application\Publication\BoundDropException;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use RuntimeException;
use Tests\Support\Fixture\EmptyArtifact;
use Tests\Support\Fixture\RunItTestKit;
use Tests\Support\Fixture\ThrowingArtifactPublisher;
use Tests\Support\Fixture\ThrowingAttachContextStorage;
use Tests\Support\Fixture\ThrowingCompletionPolicy;
use Tests\Support\Fixture\ThrowingDetachContextStorage;
use Tests\Support\Fixture\ThrowingSequenceAllocator;
use Tests\Support\Fixture\ThrowingSnapshotStore;
use Tests\Support\Fixture\ThrowingThrowableMapper;
use Tests\Support\Fixture\TransitionFailingCoordinator;
use Throwable;

/**
 * M12.7 — resilience under injected port and collaborator failures.
 *
 * These tests confirm that a failure in publication, context detachment, or
 * snapshot storage never replaces the original business result or throwable, and
 * never recurses into the failure path.
 *
 * @group application
 * @group security
 * @group resilience
 */
final class ResilienceTest extends Unit
{
    public function testPublisherFailurePreservesOperationResult(): void
    {
        $failures = [];
        $kit = new RunItTestKit(
            publisher: new ThrowingArtifactPublisher(),
            onPublicationFailure: static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            },
        );

        $result = $kit->runner->run(
            $this->definition(),
            static fn (): int => 42,
        );

        self::assertSame(42, $result);
        self::assertNotEmpty($failures, 'publisher failure must be reported');
    }

    public function testPublisherFailurePreservesOriginalThrowable(): void
    {
        $kit = new RunItTestKit(publisher: new ThrowingArtifactPublisher());

        $thrown = new RuntimeException('business broke');
        $caught = null;

        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($thrown, $caught, 'original business exception must be rethrown, not the publisher failure');
    }

    public function testContextDetachFailurePreservesResult(): void
    {
        $failures = [];
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            },
            storage: new ThrowingDetachContextStorage(),
        );

        $result = $kit->runner->run($this->definition(), static fn (): int => 7);

        self::assertSame(7, $result);
        self::assertNotEmpty($failures, 'detach failure must be reported, not swallowed silently');
    }

    public function testContextDetachFailurePreservesOriginalThrowable(): void
    {
        $kit = new RunItTestKit(storage: new ThrowingDetachContextStorage());

        $thrown = new RuntimeException('business broke');
        $caught = null;

        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($thrown, $caught, 'original business exception must survive a detach failure');
    }

    public function testSnapshotStoreFailureDoesNotAbortExecution(): void
    {
        $storeFailures = [];
        $kit = new RunItTestKit(
            snapshotStore: new ThrowingSnapshotStore(),
            onSnapshotStoreFailure: static function (Throwable $failure) use (&$storeFailures): void {
                $storeFailures[] = $failure;
            },
        );

        $result = $kit->runner->run($this->definition(), static function (ExecutionScopeInterface $scope): int {
            $scope->snapshots()->record(
                SnapshotType::fromString('test.snapshot'),
                ['state' => 'captured'],
            );

            return 99;
        });

        self::assertSame(99, $result);
        self::assertNotEmpty($storeFailures, 'snapshot store failure must be reported');
        self::assertNotEmpty(array_filter(
            $kit->artifacts(),
            static fn (ExecutionArtifactInterface $artifact): bool => $artifact instanceof SnapshotInterface,
        ), 'snapshot artifact must still be published even when the store fails');
    }

    public function testSerializerFailureDoesNotAbortOperation(): void
    {
        $kit = new RunItTestKit(snapshotStore: new ThrowingSnapshotStore());

        $result = $kit->runner->run($this->definition(), static function (ExecutionScopeInterface $scope): int {
            // A non-finite value makes the snapshot serializer reject the payload.
            $scope->snapshots()->record(
                SnapshotType::fromString('test.snapshot'),
                ['value' => INF],
            );

            return 7;
        });

        self::assertSame(7, $result, 'a snapshot serialization failure must not abort the operation');
    }

    public function testRedactorFailureIsIsolatedByFailSafePublisher(): void
    {
        $redactor = new class () implements RedactorInterface {
            public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
            {
                throw new RuntimeException('redactor is broken');
            }
        };

        $inner = new InMemoryArtifactPublisher();
        $safe = new FailSafeArtifactPublisher(
            new RedactingArtifactPublisher($redactor, $inner),
            new FailOpenPolicy(),
        );

        $safe->publish(new EmptyArtifact());

        self::assertCount(0, $inner->published(), 'redactor failure must be swallowed by the fail-safe boundary');
    }

    public function testSamplingPolicyFailureIsIsolatedByFailSafePublisher(): void
    {
        $policy = new class () implements SamplingPolicyInterface {
            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                throw new RuntimeException('sampling policy is broken');
            }
        };

        $inner = new InMemoryArtifactPublisher();
        $safe = new FailSafeArtifactPublisher(
            new SamplingArtifactPublisher($policy, $inner),
            new FailOpenPolicy(),
        );

        $safe->publish(new EmptyArtifact());

        self::assertCount(
            0,
            $inner->published(),
            'sampling policy failure must be swallowed by the fail-safe boundary'
        );
    }

    public function testCompletionPolicyFailureOnSuccessPathPreservesResult(): void
    {
        $failures = [];
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            },
            completionPolicy: new ThrowingCompletionPolicy(),
        );

        $result = $kit->runner->run($this->definition(), static fn (): int => 42);

        self::assertSame(42, $result, 'a throwing completion policy must not mask the successful result');
        self::assertNotEmpty($failures, 'the completion-policy fault must be reported to the health path');
    }

    public function testThrowingHealthCallbackPreservesResult(): void
    {
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure): never {
                throw new RuntimeException('handler is broken');
            },
        );

        $result = $kit->runner->run($this->definition(), static fn (): int => 42);

        self::assertSame(42, $result, 'a throwing health callback must not replace the business result');
    }

    public function testThrowingHealthCallbackDuringStartupPublishDoesNotAbortOperation(): void
    {
        // Every publish faults at the adapter, and every report faults at the
        // handler: the operation must still run and return its result.
        $kit = new RunItTestKit(
            publisher: new ThrowingArtifactPublisher(),
            onPublicationFailure: static function (Throwable $failure): never {
                throw new RuntimeException('handler is broken');
            },
        );

        $result = $kit->runner->run($this->definition(), static fn (): int => 7);

        self::assertSame(7, $result, 'a startup-publish fault plus handler fault must not abort the operation');
    }

    public function testThrowingHealthCallbackDuringFailurePathPreservesOriginalThrowable(): void
    {
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure): never {
                throw new RuntimeException('handler is broken');
            },
        );

        $thrown = new RuntimeException('business broke');
        $caught = null;

        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($thrown, $caught, 'original business exception must survive a throwing health callback');
    }

    public function testSequenceAllocationFaultIsReportedBeforeSurfacing(): void
    {
        $failures = [];
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            },
            sequenceAllocator: new ThrowingSequenceAllocator(),
        );

        $caught = null;

        try {
            $kit->runner->run($this->definition(), static fn (): int => 1);
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame(
            'sequence allocation is broken',
            $caught?->getMessage(),
            'setup faults still surface to the caller'
        );
        self::assertCount(1, $failures, 'a setup fault must be reported exactly once before surfacing');
    }

    public function testContextAttachFaultIsReportedBeforeSurfacing(): void
    {
        $failures = [];
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            },
            storage: new ThrowingAttachContextStorage(),
        );

        $caught = null;

        try {
            $kit->runner->run($this->definition(), static fn (): int => 1);
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame('context attach is broken', $caught?->getMessage());
        self::assertCount(1, $failures, 'an attach fault must be reported exactly once before surfacing');
    }

    public function testCompletionPolicyFailureDuringFailurePathPreservesOriginalThrowable(): void
    {
        $failures = [];
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            },
            completionPolicy: new ThrowingCompletionPolicy(),
        );

        $thrown = new RuntimeException('business broke');
        $caught = null;

        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($thrown, $caught, 'original business exception must survive a throwing completion policy');
        self::assertNotEmpty($failures, 'the completion-policy fault must be reported, not silently discarded');
    }

    public function testTransitionFailureDuringFailurePathPreservesOriginalThrowable(): void
    {
        $failures = [];
        $coordinator = new ExecutionLifecycleCoordinator();
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            },
            coordinator: new TransitionFailingCoordinator($coordinator),
        );

        $thrown = new RuntimeException('business broke');
        $caught = null;

        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($thrown, $caught, 'original business exception must survive a failing lifecycle transition');
        self::assertNotEmpty($failures, 'the transition fault must be reported to the health path');
    }

    public function testThrowableMapperFailureStillCompletesExecutionAsFailed(): void
    {
        $failures = [];
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            },
            throwableMapper: new ThrowingThrowableMapper(),
        );

        $thrown = new RuntimeException('business broke');
        $caught = null;

        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($thrown, $caught, 'original business exception must survive a throwing throwable mapper');
        self::assertNotEmpty(
            array_filter(
                $kit->artifacts(),
                static fn (ExecutionArtifactInterface $artifact): bool => $artifact instanceof SnapshotInterface,
            ),
            'execution must still reach its terminal snapshot when the mapper fails',
        );
        self::assertNotEmpty($failures, 'the mapper fault must be reported to the health path');
    }

    public function testBoundDropIsReportedThroughTheHealthPathDuringExecution(): void
    {
        $failures = [];
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            },
            limits: new SecurityLimits(maxOccurrencePayloadBytes: 5),
        );

        $result = $kit->runner->run($this->definition(), static function (ExecutionScopeInterface $scope): string {
            $scope->occurrences()->record(
                OccurrenceType::fromString('test.big.v1'),
                'source',
                ['big' => str_repeat('x', 1000)],
            );

            return 'ok';
        });

        self::assertSame('ok', $result);
        self::assertNotEmpty(
            array_filter($failures, static fn (Throwable $failure): bool => $failure instanceof BoundDropException),
            'a bound-exceeded drop must surface as a BoundDropException notice',
        );
    }

    public function testThrowingHealthCallbackOnABoundDropPreservesTheResult(): void
    {
        $kit = new RunItTestKit(
            onPublicationFailure: static function (): never {
                throw new RuntimeException('handler boom');
            },
            limits: new SecurityLimits(maxOccurrencePayloadBytes: 5),
        );

        $result = $kit->runner->run($this->definition(), static function (ExecutionScopeInterface $scope): string {
            $scope->occurrences()->record(
                OccurrenceType::fromString('test.big.v1'),
                'source',
                ['big' => str_repeat('x', 1000)],
            );

            return 'ok';
        });

        self::assertSame('ok', $result, 'a throwing health callback must never replace the business result');
    }

    public function testTransitionFailureOnTheSuccessPathIsReportedAndResultPreserved(): void
    {
        $failures = [];
        $coordinator = new TransitionFailingCoordinator(new ExecutionLifecycleCoordinator());
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            },
            coordinator: $coordinator,
        );

        $result = $kit->runner->run($this->definition(), static fn (): string => 'value');

        self::assertSame('value', $result);
        self::assertNotEmpty(
            array_filter($failures, static fn (Throwable $failure): bool => str_contains(
                $failure->getMessage(),
                'transition is broken'
            )),
            'the transition fault on the success path must be reported',
        );
    }

    private function definition(): ExecutionDefinition
    {
        return ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
        );
    }
}
