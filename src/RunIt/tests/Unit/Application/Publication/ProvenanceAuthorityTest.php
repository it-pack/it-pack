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
}
