<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Throwable;

#[CoversClass(ProvenanceAuthority::class)]
#[UsesClass(CallbackHealthReporter::class)]
#[UsesClass(RedactedArtifact::class)]
final class ProvenanceAuthorityTest extends Unit
{
    public function testEvidenceIsBoundToExactObjectAndReleasedWithExecution(): void
    {
        $authority = new ProvenanceAuthority();
        $id = $authority->issueExportId('execution', 'execution', 'exec', 'exec');
        $original = new RedactedArtifact(['id' => 'exec']);
        $copy = new RedactedArtifact(['id' => 'exec']);

        self::assertTrue($authority->attest($original, $id));
        self::assertSame($id, $authority->exportIdForArtifact($original));
        self::assertSame(
            ['id' => $id],
            $authority->exportIdentityFields($original),
        );
        self::assertNull($authority->exportIdForArtifact($copy));
        self::assertNull($authority->exportIdentityFields($copy));
        self::assertFalse($authority->attest(new RedactedArtifact(['id' => 'other']), $id));
        $authority->releaseExecution('exec');
        self::assertNull($authority->exportIdForArtifact($original));
    }

    public function testExportProjectionUsesRecordedEdgesAndOmitsUnverifiedActivity(): void
    {
        $authority = new ProvenanceAuthority();
        $root = $authority->issueExportId('execution', 'execution', 'exec', 'exec');
        $child = $authority->bindArtifact('observation', 'observation', 'exec', 'obs', 'execution-observation');
        $artifact = new RedactedArtifact(['id' => 'obs', 'execution_id' => 'exec', 'activity_id' => 'forged']);
        self::assertTrue($authority->attest($artifact, $child));
        self::assertSame(['id' => $child, 'execution_id' => $root], $authority->exportIdentityFields($artifact));
    }

    public function testActivityRelationshipsRequireUniqueSameExecutionEdges(): void
    {
        $authority = new ProvenanceAuthority();
        $root = $authority->issueExportId('execution', 'execution', 'exec', 'exec');
        $parent = $authority->bindArtifact('activity', 'activity', 'exec', 'parent', 'execution-activity');
        $child = $authority->bindArtifact('activity', 'activity', 'exec', 'child', 'execution-activity');
        $observation = $authority->bindArtifact('observation', 'observation', 'exec', 'obs', 'execution-observation');
        $activity = new RedactedArtifact(['id' => 'child', 'execution_id' => 'exec', 'parent_activity_id' => 'parent']);
        $artifact = new RedactedArtifact(['id' => 'obs', 'execution_id' => 'exec', 'activity_id' => 'child']);
        self::assertTrue($authority->attest($activity, $child));
        self::assertTrue($authority->attest($artifact, $observation));
        $authority->bindActivityRelationship($child, 'parent', true);
        $authority->bindActivityRelationship($observation, 'child');
        self::assertSame(
            ['id' => $child, 'execution_id' => $root, 'parent_activity_id' => $parent],
            $authority->exportIdentityFields($activity)
        );
        self::assertSame(
            ['id' => $observation, 'execution_id' => $root, 'activity_id' => $child],
            $authority->exportIdentityFields($artifact)
        );

        $authority->issueExportId('activity', 'activity', 'exec', 'parent');
        $other = $authority->bindArtifact('activity', 'activity', 'exec', 'other', 'execution-activity');
        $ambiguous = new RedactedArtifact([
            'id' => 'other', 'execution_id' => 'exec', 'parent_activity_id' => 'parent',
        ]);
        self::assertTrue($authority->attest($ambiguous, $other));
        $authority->bindActivityRelationship($other, 'parent', true);
        self::assertSame(['id' => $other, 'execution_id' => $root], $authority->exportIdentityFields($ambiguous));

        $foreign = $authority->issueExportId('activity', 'activity', 'foreign', 'foreign');
        self::assertNotNull($observation);
        self::assertFalse($authority->registerRelationship($foreign, $observation, 'activity-artifact'));
        $authority->releaseExecution('exec');
        self::assertNull($authority->exportIdentityFields($artifact));
    }

    public function testCapacityRejectsBindingsWithoutRetainingOrPublishingEvidence(): void
    {
        $authority = new ProvenanceAuthority(null, 2, 1, 1024);
        $authority->issueExportId('execution', 'execution', 'exec', 'exec');
        self::assertNotNull(
            $authority->bindArtifact('observation', 'observation', 'exec', 'one', 'execution-observation'),
        );
        self::assertNull(
            $authority->bindArtifact('observation', 'observation', 'exec', 'two', 'execution-observation'),
        );
        self::assertCount(2, $authority->allRecords());
        $authority->releaseExecution('exec');
        $authority->issueExportId('execution', 'execution', 'next', 'next');
        self::assertNotNull(
            $authority->bindArtifact('observation', 'observation', 'next', 'one', 'execution-observation'),
        );
    }

    public function testIdentifierByteLimitRejectsOversizedIssuance(): void
    {
        $authority = new ProvenanceAuthority(null, 10, 10, 11);
        $authority->issueExportId('execution', 'execution', 'exec', 'exec');
        self::assertNull($authority->bindArtifact(
            'observation',
            'observation',
            'exec',
            '123456789012',
            'execution-observation',
        ));
        self::assertCount(1, $authority->allRecords());
    }

    public function testTerminalSnapshotRelationshipIsRecorded(): void
    {
        $authority = new ProvenanceAuthority();
        $execution = $authority->issueExportId('execution', 'execution', 'exec', 'exec');
        $snapshot = $authority->bindArtifact(
            'snapshot',
            'terminal-snapshot',
            'exec',
            'snap',
            'execution-terminal-snapshot',
        );

        self::assertNotNull($snapshot);
        self::assertTrue($authority->verifyExportId($snapshot, 'terminal-snapshot', 'exec'));
        self::assertSame(
            ['execution-terminal-snapshot:' . $snapshot],
            $authority->allRecords()[$execution]['relationships'],
        );
    }

    public function testRejectedRelationshipReportsAndDoesNotIssueBinding(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(static function (
            Throwable $failure,
            ExecutionArtifactInterface $artifact,
        ) use (&$reports): void {
            $reports[] = [$failure->getMessage(), $artifact->normalize()];
        });
        $authority = new ProvenanceAuthority($health);
        $execution = $authority->issueExportId('execution', 'execution', 'exec', 'exec');

        self::assertNull($authority->bindArtifact(
            'snapshot',
            'snapshot',
            'exec',
            'secret-id',
            'execution-observation',
        ));
        self::assertNull($authority->bindArtifact(
            'snapshot',
            'snapshot',
            'missing',
            'secret-id',
            'execution-snapshot',
        ));
        self::assertSame([], $authority->allRecords()[$execution]['relationships']);
        self::assertCount(1, $authority->allRecords());
        self::assertCount(2, $reports);
        foreach ($reports as [$message, $artifact]) {
            self::assertSame('Provenance relationship rejected.', $message);
            self::assertStringNotContainsString('secret-id', serialize($artifact));
        }
    }

    public function testMultipleBindingsRetainTheirRelationships(): void
    {
        $authority = new ProvenanceAuthority();
        $execution = $authority->issueExportId('execution', 'execution', 'exec', 'exec');
        $first = $authority->bindArtifact('observation', 'observation', 'exec', 'one', 'execution-observation');
        $second = $authority->bindArtifact('observation', 'observation', 'exec', 'two', 'execution-observation');

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame([
            'execution-observation:' . $first,
            'execution-observation:' . $second,
        ], $authority->allRecords()[$execution]['relationships']);
    }

    public function testReleasePrunesIncomingEdgesWithoutRemovingOtherEdges(): void
    {
        $authority = new ProvenanceAuthority();
        $parent = $authority->issueExportId('execution', 'execution', 'parent', 'parent');
        $child = $authority->issueExportId('execution', 'execution', 'child', 'child');
        $other = $authority->issueExportId('execution', 'execution', 'other', 'other');
        self::assertTrue($authority->registerRelationship($parent, $child, 'parent'));
        self::assertTrue($authority->registerRelationship($parent, $other, 'link'));

        $authority->releaseExecution('child');

        self::assertSame(['link:' . $other], $authority->allRecords()[$parent]['relationships']);
        self::assertFalse($authority->verifyExportId($child, 'execution', 'child'));
    }

    public function testRepeatedChildReleaseDoesNotAccumulateParentEdges(): void
    {
        $authority = new ProvenanceAuthority();
        $parent = $authority->issueExportId('execution', 'execution', 'parent', 'parent');

        for ($i = 0; $i < 100; $i++) {
            $id = 'child-' . $i;
            $child = $authority->issueExportId('execution', 'execution', $id, $id);
            self::assertTrue($authority->registerRelationship($parent, $child, 'parent'));
            $authority->releaseExecution($id);
        }

        self::assertCount(1, $authority->allRecords());
        self::assertSame([], $authority->allRecords()[$parent]['relationships']);
    }
}
