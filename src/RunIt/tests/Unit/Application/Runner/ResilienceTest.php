<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Runner;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;

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
            publisher: new FailingArtifactPublisher(),
            onPublicationFailure: static function (\Throwable $failure) use (&$failures): void {
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
        $kit = new RunItTestKit(publisher: new FailingArtifactPublisher());

        $thrown = new RuntimeException('business broke');
        $caught = null;

        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): void {
                throw $thrown;
            });
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertSame($thrown, $caught, 'original business exception must be rethrown, not the publisher failure');
    }

    public function testContextDetachFailurePreservesResult(): void
    {
        $failures = [];
        $kit = new RunItTestKit(
            storage: new FailingContextStorage(),
            onPublicationFailure: static function (\Throwable $failure) use (&$failures): void {
                $failures[] = $failure;
            },
        );

        $result = $kit->runner->run($this->definition(), static fn (): int => 7);

        self::assertSame(7, $result);
        self::assertNotEmpty($failures, 'detach failure must be reported, not swallowed silently');
    }

    public function testContextDetachFailurePreservesOriginalThrowable(): void
    {
        $kit = new RunItTestKit(storage: new FailingContextStorage());

        $thrown = new RuntimeException('business broke');
        $caught = null;

        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): void {
                throw $thrown;
            });
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertSame($thrown, $caught, 'original business exception must survive a detach failure');
    }

    public function testSnapshotStoreFailureDoesNotAbortExecution(): void
    {
        $storeFailures = [];
        $kit = new RunItTestKit(
            snapshotStore: new FailingSnapshotStore(),
            onSnapshotStoreFailure: static function (\Throwable $failure) use (&$storeFailures): void {
                $storeFailures[] = $failure;
            },
        );

        $result = $kit->runner->run($this->definition(), static function (ExecutionScopeInterface $scope): int {
            $scope->snapshots()->record(
                \ItPack\RunIt\Domain\Snapshot\SnapshotType::fromString('test.snapshot'),
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
        $kit = new RunItTestKit(snapshotStore: new FailingSnapshotStore());

        $result = $kit->runner->run($this->definition(), static function (ExecutionScopeInterface $scope): int {
            // A non-finite value makes the snapshot serializer reject the payload.
            $scope->snapshots()->record(
                \ItPack\RunIt\Domain\Snapshot\SnapshotType::fromString('test.snapshot'),
                ['value' => INF],
            );

            return 7;
        });

        self::assertSame(7, $result, 'a snapshot serialization failure must not abort the operation');
    }

    public function testRedactorFailureIsIsolatedByFailSafePublisher(): void
    {
        $redactor = new class () implements \ItPack\RunIt\Contract\Application\Publication\RedactorInterface {
            public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
            {
                throw new RuntimeException('redactor is broken');
            }
        };

        $inner = new InMemoryArtifactPublisher();
        $safe = new \ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher(
            new \ItPack\RunIt\Application\Publication\RedactingArtifactPublisher($redactor, $inner),
            new \ItPack\RunIt\Application\Publication\FailOpenPolicy(),
        );

        $safe->publish(new FakeArtifact());

        self::assertCount(0, $inner->published(), 'redactor failure must be swallowed by the fail-safe boundary');
    }

    public function testSamplingPolicyFailureIsIsolatedByFailSafePublisher(): void
    {
        $policy = new class () implements \ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface {
            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                throw new RuntimeException('sampling policy is broken');
            }
        };

        $inner = new InMemoryArtifactPublisher();
        $safe = new \ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher(
            new \ItPack\RunIt\Application\Publication\SamplingArtifactPublisher($policy, $inner),
            new \ItPack\RunIt\Application\Publication\FailOpenPolicy(),
        );

        $safe->publish(new FakeArtifact());

        self::assertCount(0, $inner->published(), 'sampling policy failure must be swallowed by the fail-safe boundary');
    }

    private function definition(): \ItPack\RunIt\Domain\Execution\ExecutionDefinition
    {
        return \ItPack\RunIt\Domain\Execution\ExecutionDefinition::create(
            \ItPack\RunIt\Domain\Execution\ExecutionName::fromString('op'),
            \ItPack\RunIt\Domain\Execution\ExecutionKind::fromString('task'),
        );
    }
}

final class FailingArtifactPublisher implements ArtifactPublisherInterface
{
    public function publish(ExecutionArtifactInterface $artifact): void
    {
        throw new RuntimeException('publisher is broken');
    }
}

final class FailingSnapshotStore implements SnapshotStoreInterface
{
    public function store(SnapshotInterface $snapshot): void
    {
        throw new RuntimeException('snapshot store is broken');
    }
}

final class FailingContextStorage implements ContextStorageInterface
{
    public function current(): ?ExecutionScopeInterface
    {
        return null;
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        return new FakeContextToken();
    }

    public function detach(ContextTokenInterface $token): void
    {
        throw new RuntimeException('context detach is broken');
    }
}

final class FakeContextToken implements ContextTokenInterface
{
}

final class FakeArtifact implements ExecutionArtifactInterface
{
    public function normalize(): array
    {
        return [];
    }
}
