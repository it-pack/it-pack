<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionSuccess;
use ItPack\RunIt\Domain\Shared\Instant;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use RuntimeException;
use Throwable;

#[CoversClass(ProvenanceAuthority::class)]
final class ProvenanceAuthorityTest extends Unit
{
    public function testIssueExportIdReturnsExportId(): void
    {
        $authority = new ProvenanceAuthority();

        $exportId = $authority->issueExportId(
            'execution',
            'execution',
            'exec-1',
            'business-1'
        );

        self::assertStringStartsWith('runit-export-', $exportId);
    }

    public function testIssueExportIdRejectsInvalidLimits(): void
    {
        try {
            new ProvenanceAuthority(
                null,
                0,
                1,
                1,
                1
            );
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('Provenance retention limits must be positive.', $invalidArgumentException->getMessage());
        }
    }

    public function testVerifyExportIdReturnsTrueForValidBinding(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        self::assertTrue($authority->verifyExportId($exportId, 'execution', 'exec-1'));
    }

    public function testVerifyExportIdReturnsFalseForWrongRole(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        self::assertFalse($authority->verifyExportId($exportId, 'activity', 'exec-1'));
    }

    public function testVerifyExportIdReturnsFalseForWrongExecution(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        self::assertFalse($authority->verifyExportId($exportId, 'execution', 'exec-2'));
    }

    public function testVerifyExportIdReturnsFalseForReleased(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');
        $authority->releaseExecution('exec-1');

        self::assertFalse($authority->verifyExportId($exportId, 'execution', 'exec-1'));
    }

    public function testRegisterRelationshipSucceedsForValidEdge(): void
    {
        $authority = new ProvenanceAuthority();
        $from = $authority->issueExportId('execution', 'execution', 'exec-1', 'parent');
        $to = $authority->issueExportId('execution', 'execution', 'exec-1', 'child');

        self::assertTrue($authority->registerRelationship($from, $to, 'parent'));
    }

    public function testRegisterRelationshipRejectsUnknownEndpoint(): void
    {
        $authority = new ProvenanceAuthority();
        $from = $authority->issueExportId('execution', 'execution', 'exec-1', 'parent');

        self::assertFalse($authority->registerRelationship($from, 'runit-export-unknown', 'parent'));
    }

    public function testRegisterRelationshipRejectsInvalidRolePair(): void
    {
        $authority = new ProvenanceAuthority();
        $from = $authority->issueExportId('execution', 'execution', 'exec-1', 'parent');
        $to = $authority->issueExportId('execution', 'execution', 'exec-1', 'child');

        self::assertFalse($authority->registerRelationship($from, $to, 'bogus-type'));
    }

    public function testHasLiveRecordsReturnsTrueForUnreleased(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        self::assertTrue($authority->hasLiveRecords('exec-1'));
    }

    public function testHasLiveRecordsReturnsFalseAfterRelease(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');
        $authority->releaseExecution('exec-1');

        self::assertFalse($authority->hasLiveRecords('exec-1'));
    }

    public function testReleaseExecutionIsReentrantSafe(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');
        $authority->releaseExecution('exec-1');
        $authority->releaseExecution('exec-1');

        self::assertFalse($authority->hasLiveRecords('exec-1'));
    }

    public function testReportUnverifiedIsGuardedAgainstReentrancy(): void
    {
        $reporter = new class () implements InternalHealthReporterInterface {
            public bool $reported = false;

            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                $this->reported = true;
            }
        };
        $authority = new ProvenanceAuthority($reporter);
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        $authority->reportUnverified(new RuntimeException('first'));
        self::assertTrue($reporter->reported);

        $authority->reportUnverified(new RuntimeException('second'));
        self::assertTrue($reporter->reported);
    }

    public function testReportUnverifiedUsesFallbackWhenNoHealthReporter(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        $authority->reportUnverified(new RuntimeException('unverified drop'));
        self::assertStringStartsWith(
            'runit-export-',
            $authority->issueExportId('execution', 'execution', 'exec-1', 'business-2')
        );
    }

    public function testBindArtifactReturnsNullWhenRelationshipRejected(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');
        $authority->releaseExecution('exec-1');

        self::assertNull($authority->bindArtifact('snapshot', 'snapshot', 'exec-1', 'snap-1', 'execution-snapshot'));
    }

    public function testExportIdForBusinessIdExcludingReturnsNullForNoMatch(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        self::assertNull($authority->exportIdForBusinessIdExcluding('business-1', 'exec-1'));
    }

    public function testBusinessIdForExportIdReturnsNullForReleased(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');
        $authority->releaseExecution('exec-1');

        self::assertNull($authority->businessIdForExportId($exportId));
    }

    public function testExecutionIdForExportIdReturnsNullForReleased(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');
        $authority->releaseExecution('exec-1');

        self::assertNull($authority->executionIdForExportId($exportId));
    }

    public function testAttestReturnsTrueForValidExecution(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $execution = Execution::create(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1000),
        )->beginRunning()->complete(ExecutionSuccess::create(), Instant::of(2000));

        self::assertTrue($authority->attest($execution, $exportId));
    }

    public function testAttestReturnsFalseForNullExportId(): void
    {
        $authority = new ProvenanceAuthority();

        $execution = Execution::create(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1000),
        )->beginRunning()->complete(ExecutionSuccess::create(), Instant::of(2000));

        self::assertFalse($authority->attest($execution, null));
    }

    public function testExportIdForArtifactReturnsNullForUnattested(): void
    {
        $authority = new ProvenanceAuthority();

        $execution = Execution::create(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1000),
        )->beginRunning()->complete(ExecutionSuccess::create(), Instant::of(2000));

        self::assertNull($authority->exportIdForArtifact($execution));
    }

    public function testAttestSuccessorReturnsFalseForUnknownPrevious(): void
    {
        $authority = new ProvenanceAuthority();

        $previous = Execution::create(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1000),
        )->beginRunning()->complete(ExecutionSuccess::create(), Instant::of(2000));

        $successor = Execution::create(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(2000),
        )->beginRunning()->complete(ExecutionSuccess::create(), Instant::of(3000));

        self::assertFalse($authority->attestSuccessor($previous, $successor));
    }

    public function testExportIdentityFieldsReturnsNullForUnattested(): void
    {
        $authority = new ProvenanceAuthority();

        $execution = Execution::create(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1000),
        )->beginRunning()->complete(ExecutionSuccess::create(), Instant::of(2000));

        self::assertNull($authority->exportIdentityFields($execution));
    }

    public function testBindActivityRelationshipWithNullIdsIsNoOp(): void
    {
        $authority = new ProvenanceAuthority();

        $authority->bindActivityRelationship();
        $authority->bindActivityRelationship('runit-export-abc');
        $authority->bindActivityRelationship(null, 'runit-export-def');

        self::assertSame([], $authority->allRecords());
    }

    public function testAllRecordsReturnsEmptyInitially(): void
    {
        $authority = new ProvenanceAuthority();

        self::assertSame([], $authority->allRecords());
    }

    public function testExportIdForBusinessIdWithRoleFilter(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');
        $authority->issueExportId('activity', 'activity', 'exec-1', 'business-1');

        self::assertNotNull($authority->exportIdForBusinessId('business-1', 'exec-1', 'execution'));
        self::assertNotNull($authority->exportIdForBusinessId('business-1', 'exec-1', 'activity'));
    }

    public function testIssueExportIdRejectsNonEmptyRelationships(): void
    {
        $authority = new ProvenanceAuthority();

        try {
            $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1', ['parent:other']);
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $runtimeException) {
            self::assertStringContainsString('Provenance capacity exceeded', $runtimeException->getMessage());
        }
    }

    public function testBindArtifactReturnsNullWhenRelationshipResolutionFails(): void
    {
        $authority = new ProvenanceAuthority();

        // No execution export ID exists for exec-1, so relationship resolution fails
        self::assertNull($authority->bindArtifact('snapshot', 'snapshot', 'exec-1', 'snap-1', 'execution-snapshot'));
    }

    public function testBindArtifactReturnsExportIdOnSuccess(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $exportId = $authority->bindArtifact('snapshot', 'snapshot', 'exec-1', 'snap-1', 'execution-snapshot');

        self::assertNotNull($exportId);
        self::assertStringStartsWith('runit-export-', $exportId);
    }

    public function testAttestReturnsFalseWhenNormalizeThrows(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $artifact = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                throw new RuntimeException('normalize failed');
            }
        };

        self::assertFalse($authority->attest($artifact, $exportId));
    }

    public function testExportIdForArtifactReturnsNullWhenNormalizeThrows(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $artifact = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                throw new RuntimeException('normalize failed');
            }
        };

        $authority->attest($artifact, $exportId);

        self::assertNull($authority->exportIdForArtifact($artifact));
    }

    public function testAttestSuccessorReturnsFalseWhenNormalizeThrows(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $artifact = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                throw new RuntimeException('normalize failed');
            }
        };

        $authority->attest($artifact, $exportId);

        $successor = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                throw new RuntimeException('normalize failed');
            }
        };

        self::assertFalse($authority->attestSuccessor($artifact, $successor));
    }

    public function testIssueExportIdThrowsWhenReleasing(): void
    {
        $authority = new ProvenanceAuthority();
        $reflection = new ReflectionClass($authority);
        $releasing = $reflection->getProperty('releasing');
        $releasing->setValue($authority, true);

        try {
            $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $runtimeException) {
            self::assertStringContainsString('no new issuance permitted', $runtimeException->getMessage());
        }
    }

    public function testRegisterRelationshipRejectsReleasedEndpoint(): void
    {
        $authority = new ProvenanceAuthority();
        $from = $authority->issueExportId('execution', 'execution', 'exec-1', 'parent');
        $authority->releaseExecution('exec-1');

        self::assertFalse($authority->registerRelationship($from, $from, 'parent'));
    }

    public function testRegisterRelationshipEdgeCapacityExceeded(): void
    {
        $authority = new ProvenanceAuthority(
            null,
            100,
            1,
            1024,
            100
        );
        $from = $authority->issueExportId('execution', 'execution', 'exec-1', 'parent');
        $to = $authority->issueExportId('execution', 'execution', 'exec-1', 'child');

        $authority->registerRelationship($from, $to, 'parent');

        self::assertFalse($authority->registerRelationship($from, $to, 'link'));
    }

    public function testBindActivityRelationshipNullTargetIdIsNoOp(): void
    {
        $authority = new ProvenanceAuthority();

        $authority->bindActivityRelationship(null, 'runit-export-activity');

        self::assertSame([], $authority->allRecords());
    }

    public function testBindActivityRelationshipNullActivityIdIsNoOp(): void
    {
        $authority = new ProvenanceAuthority();

        $authority->bindActivityRelationship('runit-export-target');

        self::assertSame([], $authority->allRecords());
    }

    public function testBindActivityRelationshipUnknownRecordsIsNoOp(): void
    {
        $authority = new ProvenanceAuthority();

        $authority->bindActivityRelationship('runit-export-unknown-target', 'runit-export-unknown-activity');

        self::assertSame([], $authority->allRecords());
    }

    public function testBindActivityRelationshipReleasedRecordsIsNoOp(): void
    {
        $authority = new ProvenanceAuthority();
        $target = $authority->issueExportId('execution', 'execution', 'exec-1', 'target');
        $activity = $authority->issueExportId('activity', 'activity', 'exec-1', 'activity');
        $authority->releaseExecution('exec-1');

        $authority->bindActivityRelationship($target, $activity);

        self::assertSame([], $authority->allRecords());
    }

    public function testBindActivityRelationshipDifferentExecutionIdsIsNoOp(): void
    {
        $authority = new ProvenanceAuthority();
        $target = $authority->issueExportId('execution', 'execution', 'exec-1', 'target');
        $activity = $authority->issueExportId('activity', 'activity', 'exec-2', 'activity');

        $authority->bindActivityRelationship($target, $activity);

        $records = $authority->allRecords();
        self::assertArrayHasKey($target, $records);
        self::assertArrayHasKey($activity, $records);
        self::assertSame([], $records[$target]['relationships']);
    }

    public function testBindActivityRelationshipWithParentType(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'parent-exec');

        $from = $authority->issueExportId('activity', 'activity', 'exec-1', 'activity');
        $to = $authority->issueExportId('activity', 'activity', 'exec-1', 'child-activity');

        $authority->registerRelationship($from, $to, ProvenanceAuthority::KEY_ACTIVITY_PARENT);

        self::assertTrue($authority->registerRelationship($from, $to, ProvenanceAuthority::KEY_ACTIVITY_PARENT));
    }

    public function testExportIdForBusinessIdMultipleMatchReturnsNull(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');
        $authority->issueExportId('activity', 'activity', 'exec-1', 'business-1');

        self::assertNull($authority->exportIdForBusinessId('business-1', 'exec-1'));
    }

    public function testExportIdForBusinessIdExcludingMultipleMatchReturnsNull(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');
        $authority->issueExportId('execution', 'execution', 'exec-2', 'business-1');
        $authority->issueExportId('execution', 'execution', 'exec-3', 'business-1');

        self::assertNull($authority->exportIdForBusinessIdExcluding('business-1', 'exec-1'));
    }

    public function testBindArtifactReleasingReturnsNull(): void
    {
        $authority = new ProvenanceAuthority();
        $reflection = new ReflectionClass($authority);
        $releasing = $reflection->getProperty('releasing');
        $releasing->setValue($authority, true);

        self::assertNull($authority->bindArtifact('snapshot', 'snapshot', 'exec-1', 'snap-1', 'execution-snapshot'));
    }

    public function testBindArtifactCatchesIssuanceFailure(): void
    {
        $authority = new ProvenanceAuthority(
            null,
            1,
            100,
            1024,
            1
        );
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        self::assertNull($authority->bindArtifact('snapshot', 'snapshot', 'exec-1', 'snap-1', ''));
    }

    public function testBindArtifactCatchesRelationshipFailure(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        self::assertNull($authority->bindArtifact('snapshot', 'snapshot', 'exec-1', 'snap-1', 'bogus-type'));
    }

    public function testReleaseExecutionReentrancyGuard(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');
        $authority->releaseExecution('exec-1');

        $reflection = new ReflectionClass($authority);
        $releasing = $reflection->getProperty('releasing');
        $releasing->setValue($authority, true);

        $authority->releaseExecution('exec-1');

        self::assertFalse($authority->hasLiveRecords('exec-1'));
    }

    public function testReportUnverifiedSafelyHealthReporterThrows(): void
    {
        $reporter = new class () implements InternalHealthReporterInterface {
            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                throw new RuntimeException('health reporter failed');
            }
        };
        $authority = new ProvenanceAuthority($reporter);
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        $result = $authority->bindArtifact('snapshot', 'snapshot', 'exec-1', 'snap-1', 'bogus-type');

        self::assertNull($result);
    }

    public function testReportUnverifiedSafelyFallbackThrows(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        $reflection = new ReflectionClass($authority);
        $reporting = $reflection->getProperty('reporting');
        $reporting->setValue($authority, true);

        $result = $authority->bindArtifact('snapshot', 'snapshot', 'exec-1', 'snap-1', 'bogus-type');

        self::assertNull($result);
    }

    public function testRemoveBusinessExecutionIndexNonStringValues(): void
    {
        $authority = new ProvenanceAuthority();
        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('removeBusinessExecutionIndex');

        $method->invoke($authority, ['businessId' => null, 'executionId' => 'exec-1'], 'runit-export-abc');

        self::assertSame([], $authority->allRecords());
    }

    public function testActivityIdentityFieldsNoMatch(): void
    {
        $authority = new ProvenanceAuthority();
        $activityId = $authority->issueExportId('activity', 'activity', 'exec-1', 'activity');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('activityIdentityFields');

        $result = $method->invoke($authority, $activityId, ['activity_id' => 'nonexistent']);

        self::assertSame([], $result);
    }

    public function testExportNonExecutionIdentityFieldsMissingRelationship(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $snapshotId = $authority->issueExportId('snapshot', 'snapshot', 'exec-1', 'snap-1');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('exportNonExecutionIdentityFields');

        $result = $method->invoke(
            $authority,
            ['role' => 'snapshot', 'executionId' => 'exec-1'],
            ['id' => 'snap-1'],
            $snapshotId,
            []
        );

        self::assertNull($result);
    }
}
