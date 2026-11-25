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
use ItPack\RunIt\Domain\Shared\ArtifactField;
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
            public int $calls = 0;

            public ?ProvenanceAuthority $authority = null;

            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                $this->calls++;
                $this->authority?->reportUnverified(new RuntimeException('nested'));
            }
        };
        $authority = new ProvenanceAuthority($reporter);
        $reporter->authority = $authority;
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        $authority->reportUnverified(new RuntimeException('first'));
        self::assertSame(1, $reporter->calls);
    }

    public function testReportUnverifiedUsesFallbackWhenNoHealthReporter(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        $authority->reportUnverified(new RuntimeException('unverified drop'));

        self::assertTrue($authority->hasLiveRecords('exec-1'));
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
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $authority->bindActivityRelationship();
        $authority->bindActivityRelationship('runit-export-abc');
        $authority->bindActivityRelationship(null, 'runit-export-def');

        self::assertTrue($authority->verifyExportId($exportId, 'execution', 'exec-1'));
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

    public function testRegisterRelationshipRejectsWhenMaxEdgesReached(): void
    {
        $authority = new ProvenanceAuthority(null, 100000, 1, 1024, 100000);
        $from = $authority->issueExportId('execution', 'execution', 'exec-1', 'parent');
        $to = $authority->issueExportId('execution', 'execution', 'exec-1', 'child');

        self::assertTrue($authority->registerRelationship($from, $to, 'parent'));

        $from2 = $authority->issueExportId('execution', 'execution', 'exec-1', 'parent2');
        $to2 = $authority->issueExportId('execution', 'execution', 'exec-1', 'child2');

        self::assertFalse($authority->registerRelationship($from2, $to2, 'parent'));
    }

    public function testIssueExportIdRejectsWhenMaxRecordsPerExecutionReached(): void
    {
        $authority = new ProvenanceAuthority(null, 1, 100000, 1024, 100000);
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        try {
            $authority->issueExportId('execution', 'execution', 'exec-1', 'business-2');
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $runtimeException) {
            self::assertStringContainsString('Provenance capacity exceeded', $runtimeException->getMessage());
        }
    }

    public function testIssueExportIdRejectsWhenMaxTotalRecordsReached(): void
    {
        $authority = new ProvenanceAuthority(null, 100000, 100000, 1024, 1);
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        try {
            $authority->issueExportId('execution', 'execution', 'exec-2', 'business-2');
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $runtimeException) {
            self::assertStringContainsString('Provenance capacity exceeded', $runtimeException->getMessage());
        }
    }

    public function testIssueExportIdRejectsWhenIdentifierTooLong(): void
    {
        $authority = new ProvenanceAuthority(null, 100000, 100000, 5, 100000);

        try {
            $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $runtimeException) {
            self::assertStringContainsString('Provenance capacity exceeded', $runtimeException->getMessage());
        }
    }

    public function testRegisterRelationshipRejectsCrossExecutionActivityEdge(): void
    {
        $authority = new ProvenanceAuthority();
        $from = $authority->issueExportId('activity', 'activity', 'exec-1', 'act-1');
        $to = $authority->issueExportId('observation', 'observation', 'exec-2', 'obs-1');

        self::assertFalse($authority->registerRelationship($from, $to, 'activity-artifact'));
    }

    public function testRegisterRelationshipRejectsSelfReferencingActivityEdge(): void
    {
        $authority = new ProvenanceAuthority();
        $from = $authority->issueExportId('activity', 'activity', 'exec-1', 'act-1');

        self::assertFalse($authority->registerRelationship($from, $from, ProvenanceAuthority::KEY_ACTIVITY_PARENT));
    }

    public function testAttestSuccessorReturnsTrueForValidSuccessor(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $previous = new class () implements ExecutionArtifactInterface {
            /** @var array<string, mixed> */
            public array $fields = [
                ArtifactField::ID => 'exec-1',
                ArtifactField::EXECUTION_ID => 'exec-1',
            ];

            /** @return array<string, mixed> */
            public function normalize(): array
            {
                return $this->fields;
            }
        };
        $successor = new class () implements ExecutionArtifactInterface {
            /** @var array<string, mixed> */
            public array $fields = [
                ArtifactField::ID => 'exec-1',
                ArtifactField::EXECUTION_ID => 'exec-1',
            ];

            /** @return array<string, mixed> */
            public function normalize(): array
            {
                return $this->fields;
            }
        };

        self::assertTrue($authority->attest($previous, $exportId));
        self::assertTrue($authority->attestSuccessor($previous, $successor));
    }

    public function testExportIdentityFieldsReturnsFieldsForAttestedArtifact(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $artifact = new class () implements ExecutionArtifactInterface {
            /** @var array<string, mixed> */
            public array $fields = [
                ArtifactField::ID => 'exec-1',
                ArtifactField::EXECUTION_ID => 'exec-1',
            ];

            /** @return array<string, mixed> */
            public function normalize(): array
            {
                return $this->fields;
            }
        };

        self::assertTrue($authority->attest($artifact, $exportId));
        $fields = $authority->exportIdentityFields($artifact);

        self::assertNotNull($fields);
        self::assertArrayHasKey(ArtifactField::ID, $fields);
    }

    public function testExportIdForArtifactReturnsNullWhenIdentityFieldsChange(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $artifact = new class () implements ExecutionArtifactInterface {
            /** @var array<string, mixed> */
            public array $fields = [
                ArtifactField::ID => 'exec-1',
                ArtifactField::EXECUTION_ID => 'exec-1',
            ];

            /** @return array<string, mixed> */
            public function normalize(): array
            {
                return $this->fields;
            }
        };

        self::assertTrue($authority->attest($artifact, $exportId));
        self::assertSame($exportId, $authority->exportIdForArtifact($artifact));

        $artifact->fields[ArtifactField::ID] = 'changed';
        self::assertNull($authority->exportIdForArtifact($artifact));
    }

    public function testReleaseExecutionRemovesRecordsAndEdges(): void
    {
        $authority = new ProvenanceAuthority();
        $executionId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');
        $artifactId = $authority->issueExportId('observation', 'observation', 'exec-1', 'obs-1');

        self::assertTrue($authority->registerRelationship($executionId, $artifactId, 'execution-observation'));

        $authority->releaseExecution('exec-1');

        self::assertFalse($authority->verifyExportId($executionId, 'execution', 'exec-1'));
        self::assertFalse($authority->verifyExportId($artifactId, 'observation', 'exec-1'));
    }

    public function testAttestReturnsFalseWhenBusinessIdMismatchForNonMeasurement(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('activity', 'activity', 'exec-1', 'business-1');

        $artifact = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [ArtifactField::EXECUTION_ID => 'exec-1', ArtifactField::ID => 'different-business-id'];
            }
        };

        self::assertFalse($authority->attest($artifact, $exportId));
    }

    public function testExportIdForArtifactReturnsNullWhenNormalizeThrowsAfterAttest(): void
    {
        $authority = new ProvenanceAuthority();
        $artifact = new class () implements ExecutionArtifactInterface {
            private bool $throw = false;

            public function normalize(): array
            {
                if ($this->throw) {
                    throw new RuntimeException('normalize failed');
                }

                return [ArtifactField::EXECUTION_ID => 'exec-1', ArtifactField::ID => 'exec-1'];
            }

            public function setThrow(bool $throw): void
            {
                $this->throw = $throw;
            }
        };
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');
        $authority->attest($artifact, $exportId);
        $artifact->setThrow(true);

        self::assertNull($authority->exportIdForArtifact($artifact));
    }

    public function testAttestSuccessorReturnsFalseWhenIdentityFieldsThrow(): void
    {
        $authority = new ProvenanceAuthority();
        $previous = new class () implements ExecutionArtifactInterface {
            private bool $throw = false;

            public function normalize(): array
            {
                if ($this->throw) {
                    throw new RuntimeException('normalize failed');
                }

                return [ArtifactField::EXECUTION_ID => 'exec-1', ArtifactField::ID => 'exec-1'];
            }

            public function setThrow(bool $throw): void
            {
                $this->throw = $throw;
            }
        };
        $successor = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [ArtifactField::EXECUTION_ID => 'exec-1', ArtifactField::ID => 'exec-1'];
            }
        };
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');
        $authority->attest($previous, $exportId);
        $previous->setThrow(true);

        self::assertFalse($authority->attestSuccessor($previous, $successor));
    }

    public function testRegisterRelationshipRejectsExecutionIdMismatchForExecutionType(): void
    {
        $authority = new ProvenanceAuthority();
        $from = $authority->issueExportId('execution', 'execution', 'exec-1', 'parent');
        $to = $authority->issueExportId('activity', 'activity', 'exec-2', 'child');

        self::assertFalse($authority->registerRelationship($from, $to, 'execution-activity'));
    }

    public function testActivityIdentityFieldsWithMatch(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'parent-exec');

        $parentId = $authority->issueExportId('activity', 'activity', 'exec-1', 'parent-activity');
        $childId = $authority->issueExportId('activity', 'activity', 'exec-1', 'child-activity');

        $authority->registerRelationship($parentId, $childId, 'activity-parent');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('activityIdentityFields');

        $result = $method->invoke($authority, $childId, [ArtifactField::PARENT_ACTIVITY_ID => 'parent-activity']);

        self::assertSame([ArtifactField::PARENT_ACTIVITY_ID => $parentId], $result);
    }

    public function testLinkedExecutionIdsReturnsLinks(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'parent-exec');

        $targetId = $authority->issueExportId('execution', 'execution', 'exec-1', 'linked-exec');

        $authority->registerRelationship(
            $targetId,
            $authority->issueExportId('execution', 'execution', 'exec-1', 'source-exec'),
            'link',
        );

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('linkedExecutionIds');

        $result = $method->invoke($authority, $targetId, ['parent_execution_id' => 'parent-exec']);

        self::assertIsArray($result);
    }

    public function testCollectParentExecutionIdsReturnsParents(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'parent-exec');

        $targetId = $authority->issueExportId('execution', 'execution', 'exec-1', 'child-exec');

        $authority->registerRelationship(
            $targetId,
            $authority->issueExportId('execution', 'execution', 'exec-1', 'source-exec'),
            'parent',
        );

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('collectParentExecutionIds');

        $result = $method->invoke($authority, $targetId, [ArtifactField::PARENT_EXECUTION_ID => 'parent-exec']);

        self::assertIsArray($result);
    }

    public function testExportExecutionIdentityFieldsWithLinkedClaims(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'parent-exec');

        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'child-exec');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('exportExecutionIdentityFields');

        $result = $method->invoke(
            $authority,
            [ArtifactField::LINKED_EXECUTION_IDS => ['parent-exec']],
            $exportId,
            []
        );

        self::assertIsArray($result);
    }

    public function testExportIdentityFieldsReturnsNullWhenRecordMissing(): void
    {
        $authority = new ProvenanceAuthority();
        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('exportIdentityFields');

        $artifact = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [];
            }
        };

        $result = $method->invoke($authority, $artifact);

        self::assertNull($result);
    }

    public function testExportExecutionIdentityFieldsReturnsOutputWhenNoLinks(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('exportExecutionIdentityFields');

        $result = $method->invoke(
            $authority,
            [],
            $exportId,
            []
        );

        self::assertSame([], $result);
    }

    public function testBindActivityRelationshipReleasedRecords(): void
    {
        $authority = new ProvenanceAuthority();
        $activityId = $authority->issueExportId('activity', 'activity', 'exec-1', 'activity');
        $targetId = $authority->issueExportId('observation', 'observation', 'exec-1', 'obs-1');

        $reflection = new ReflectionClass($authority);
        $releasing = $reflection->getProperty('releasing');
        $releasing->setValue($authority, true);

        $authority->bindActivityRelationship($targetId, $activityId, false);

        self::assertSame($activityId, $activityId);
    }

    public function testActivityIdentityFieldsNoIncoming(): void
    {
        $authority = new ProvenanceAuthority();
        $activityId = $authority->issueExportId('activity', 'activity', 'exec-1', 'activity');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('activityIdentityFields');

        $result = $method->invoke($authority, $activityId, [ArtifactField::PARENT_ACTIVITY_ID => 'x']);

        self::assertSame([], $result);
    }

    public function testLinkedExecutionIdsNoLinks(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('linkedExecutionIds');

        $result = $method->invoke($authority, $exportId, []);

        self::assertSame([], $result);
    }

    public function testExportNonExecutionIdentityFieldsRoleSnapshot(): void
    {
        $authority = new ProvenanceAuthority();
        $executionId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');
        $exportId = $authority->issueExportId('snapshot', 'snapshot', 'exec-1', 'snap-1');
        $authority->registerRelationship($executionId, $exportId, 'execution-snapshot');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('exportNonExecutionIdentityFields');

        $result = $method->invoke(
            $authority,
            ['executionId' => 'exec-1', 'role' => 'snapshot'],
            [],
            $exportId,
            []
        );

        self::assertIsArray($result);
    }

    public function testCollectParentExecutionIdsNoParents(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('collectParentExecutionIds');

        $result = $method->invoke($authority, $exportId, []);

        self::assertSame([], $result);
    }

    public function testReleaseCandidateNoIncoming(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('releaseCandidate');

        $method->invoke($authority, $exportId);

        self::assertSame($exportId, $exportId);
    }

    public function testAttestSuccessorReturnsFalseForIdentityFieldsMismatch(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $previous = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [ArtifactField::ID => 'exec-1', ArtifactField::EXECUTION_ID => 'exec-1'];
            }
        };
        $successor = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [ArtifactField::ID => 'other', ArtifactField::EXECUTION_ID => 'exec-1'];
            }
        };

        $authority->attest($previous, $exportId);

        self::assertFalse($authority->attestSuccessor($previous, $successor));
    }

    public function testBindActivityRelationshipReleasedRecordsIsNoopWithoutReentrancyGuard(): void
    {
        $authority = new ProvenanceAuthority();
        $activityId = $authority->issueExportId('activity', 'activity', 'exec-1', 'activity');
        $targetId = $authority->issueExportId('observation', 'observation', 'exec-1', 'obs-1');
        $authority->releaseExecution('exec-1');

        $authority->bindActivityRelationship($targetId, $activityId, false);

        self::assertSame($activityId, $activityId);
    }

    public function testBusinessIdForExportIdReturnsNullForUnknownId(): void
    {
        $authority = new ProvenanceAuthority();

        self::assertNull($authority->businessIdForExportId('runit-export-unknown'));
    }

    public function testExecutionIdForExportIdReturnsNullForUnknownId(): void
    {
        $authority = new ProvenanceAuthority();

        self::assertNull($authority->executionIdForExportId('runit-export-unknown'));
    }

    public function testBindArtifactReturnsNullWhenReleasing(): void
    {
        $authority = new ProvenanceAuthority();
        $reflection = new ReflectionClass($authority);
        $releasing = $reflection->getProperty('releasing');
        $releasing->setValue($authority, true);

        self::assertNull($authority->bindArtifact('snapshot', 'snapshot', 'exec-1', 'snap-1', 'execution-snapshot'));
    }

    public function testBindArtifactCatchesIssuanceCapacityFailure(): void
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

    public function testReportUnverifiedIsGuardedAgainstConcurrentReentry(): void
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

        $reflection = new ReflectionClass($authority);
        $reporting = $reflection->getProperty('reporting');
        $reporting->setValue($authority, true);

        $authority->reportUnverified(new RuntimeException('concurrent'));

        self::assertFalse($reporter->reported);
    }

    public function testReportUnverifiedSafelyUsesFallbackWithoutHealthReporter(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('reportUnverifiedSafely');

        $method->invoke($authority);

        self::assertCount(1, $authority->allRecords());
    }

    public function testReportUnverifiedSafelyFallsBackWhenHealthReporterThrows(): void
    {
        $reporter = new class () implements InternalHealthReporterInterface {
            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                throw new RuntimeException('health reporter failed');
            }
        };
        $authority = new ProvenanceAuthority($reporter);
        $authority->issueExportId('execution', 'execution', 'exec-1', 'business-1');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('reportUnverifiedSafely');

        $method->invoke($authority);

        self::assertCount(1, $authority->allRecords());
    }

    public function testExportExecutionIdentityFieldsIgnoresNonArrayLinkedClaims(): void
    {
        $authority = new ProvenanceAuthority();
        $exportId = $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('exportExecutionIdentityFields');

        $result = $method->invoke(
            $authority,
            [ArtifactField::LINKED_EXECUTION_IDS => 'not-array'],
            $exportId,
            []
        );

        self::assertSame([], $result);
    }

    public function testCollectParentExecutionIdsIgnoresNonExecutionSource(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $childId = $authority->issueExportId('execution', 'execution', 'exec-1', 'child');
        $authority->issueExportId('activity', 'activity', 'exec-1', 'source');

        $authority->registerRelationship(
            $childId,
            $authority->issueExportId(
                'activity',
                'activity',
                'exec-1',
                'source'
            ),
            'parent'
        );

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('collectParentExecutionIds');

        $result = $method->invoke($authority, $childId, [ArtifactField::PARENT_EXECUTION_ID => 'exec-1']);

        self::assertSame([], $result);
    }

    public function testReleaseCandidateRemovesOutgoingEdges(): void
    {
        $authority = new ProvenanceAuthority();
        $from = $authority->issueExportId('execution', 'execution', 'exec-1', 'from');
        $to = $authority->issueExportId('execution', 'execution', 'exec-1', 'to');
        $authority->registerRelationship($from, $to, 'link');

        $reflection = new ReflectionClass($authority);
        $method = $reflection->getMethod('releaseCandidate');

        $method->invoke($authority, $from);

        $records = $authority->allRecords();
        self::assertArrayNotHasKey($from, $records);
        self::assertSame([], $records[$to]['relationships'] ?? []);
    }
}
