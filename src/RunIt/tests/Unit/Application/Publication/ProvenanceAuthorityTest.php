<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\FallbackErrorLogReporter;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
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
        $authority->bindActivityRelationship($child, $parent, true);
        $authority->bindActivityRelationship($observation, $child);
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
        $authority->bindActivityRelationship($other, $parent, true);
        self::assertSame(
            ['id' => $other, 'execution_id' => $root, 'parent_activity_id' => $parent],
            $authority->exportIdentityFields($ambiguous)
        );

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

    public function testRecordCapacityIsIndependentPerLiveExecution(): void
    {
        $authority = new ProvenanceAuthority(null, 2, 10);
        $authority->issueExportId('execution', 'execution', 'first', 'first');
        self::assertNotNull($authority->bindArtifact('observation', 'observation', 'first', 'one'));
        self::assertNull($authority->bindArtifact('observation', 'observation', 'first', 'two'));

        $second = $authority->issueExportId('execution', 'execution', 'second', 'second');
        self::assertNotNull($authority->bindArtifact('observation', 'observation', 'second', 'one'));
        self::assertNull($authority->bindArtifact('observation', 'observation', 'second', 'two'));
        self::assertTrue($authority->verifyExportId($second, 'execution', 'second'));

        $authority->releaseExecution('first');
        self::assertNull($authority->bindArtifact('observation', 'observation', 'second', 'three'));
        $authority->issueExportId('execution', 'execution', 'first', 'first');
        self::assertNotNull($authority->bindArtifact('observation', 'observation', 'first', 'new'));
    }

    public function testRecordLimitMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProvenanceAuthority(null, 0);
    }

    public function testAggregateCapacityDropsAndReportsUntilRecordsAreReleased(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(static function (
            Throwable $failure,
            ExecutionArtifactInterface $artifact,
        ) use (&$reports): void {
            $reports[] = [$failure->getMessage(), $artifact->normalize()];
        });
        $authority = new ProvenanceAuthority($health, 10, 10, 1024, 2);
        $first = $authority->issueExportId('execution', 'execution', 'first', 'first');
        $second = $authority->issueExportId('execution', 'execution', 'second', 'second');
        $artifact = new RedactedArtifact(['id' => 'secret', 'execution_id' => 'second']);

        self::assertNull($authority->bindArtifact(
            'observation',
            'observation',
            'second',
            'secret',
            'execution-observation',
        ));
        self::assertFalse($authority->attest($artifact, null));
        self::assertNull($authority->exportIdentityFields($artifact));
        self::assertNull($authority->exportIdForBusinessId('secret', 'second'));
        self::assertCount(2, $authority->allRecords());
        self::assertSame('provenance verification or issuance failed.', $reports[0][0]);
        self::assertStringNotContainsString('secret', serialize($reports));

        $authority->releaseExecution('first');
        self::assertFalse($authority->verifyExportId($first, 'execution', 'first'));
        self::assertNull($authority->exportIdForBusinessId('first', 'first'));
        $issued = $authority->bindArtifact('observation', 'observation', 'second', 'secret', 'execution-observation');
        self::assertNotNull($issued);
        self::assertTrue($authority->attest($artifact, $issued));
        self::assertSame(['id' => $issued, 'execution_id' => $second], $authority->exportIdentityFields($artifact));
        self::assertCount(2, $authority->allRecords());
    }

    public function testAggregateRecordLimitMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProvenanceAuthority(null, 10, 10, 1024, 0);
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
            self::assertSame('provenance verification or issuance failed.', $message);
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

    public function testBusinessLookupRejectsCompositeKeyCollisionsAndAmbiguousExecutions(): void
    {
        $authority = new ProvenanceAuthority();
        $first = $authority->issueExportId('execution', 'execution', "b\0c", 'a');
        $second = $authority->issueExportId('execution', 'execution', 'c', "a\0b");
        self::assertSame($first, $authority->exportIdForBusinessId('a', "b\0c", 'execution'));
        self::assertSame($second, $authority->exportIdForBusinessId("a\0b", 'c', 'execution'));
        self::assertSame($first, $authority->exportIdForBusinessIdExcluding('a', 'other'));
        $authority->issueExportId('execution', 'execution', 'another', 'a');
        self::assertNull($authority->exportIdForBusinessIdExcluding('a', 'other'));
        $authority->releaseExecution('another');
        self::assertSame($first, $authority->exportIdForBusinessIdExcluding('a', 'other'));
    }

    public function testUnverifiedReportContainsNoCallerPayloadAndGuardsReentry(): void
    {
        $previousIgnoreArgs = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        try {
            $health = new class () implements InternalHealthReporterInterface {
                public ?ProvenanceAuthority $authority = null;

                /** @var list<array{string, array<string, mixed>}> */
                public array $reports = [];

                /** @var list<Throwable> */
                public array $errors = [];

                public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
                {
                    $this->reports[] = [$error->getMessage(), $artifact->normalize()];
                    $this->errors[] = $error;
                    $this->authority?->reportUnverified(new RuntimeException('nested-secret'), 'nested-secret');
                }
            };
            $authority = new ProvenanceAuthority($health);
            $health->authority = $authority;
            $authority->reportUnverified(new RuntimeException('exception-secret'), 'identifier-secret');
            self::assertCount(1, $health->reports);
            self::assertStringNotContainsString('secret', serialize($health->reports));
            foreach ($health->errors as $error) {
                $this->assertTraceContainsNoSecrets($error->getTrace(), 'secret', 'identifier-secret');
            }

            $authority->reportUnverified(new RuntimeException('again'));
            self::assertCount(2, $health->reports);
        } finally {
            if ($previousIgnoreArgs === false) {
                ini_restore('zend.exception_ignore_args');
            } else {
                ini_set('zend.exception_ignore_args', $previousIgnoreArgs);
            }
        }
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

    public function testReportUnverifiedWithoutHealthReporterUsesErrorLogFallback(): void
    {
        $logged = '';
        $logTo = static function (string $message) use (&$logged): void {
            $logged = $message;
        };
        $fallback = new FallbackErrorLogReporter($logTo);
        $fallback->report(
            new RuntimeException('unverified'),
            new RuntimeException('no health reporter configured'),
            'redacted',
        );

        self::assertStringContainsString('[runit] publication health fallback:', $logged);
        self::assertStringContainsString('unverified', $logged);
        self::assertStringContainsString('artifact=redacted', $logged);
    }

    public function testReportUnverifiedWithThrowingHealthReporterFallsBackAndStaysUsable(): void
    {
        $health = new readonly class () implements InternalHealthReporterInterface {
            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                throw new RuntimeException('health reporter failure');
            }
        };
        $authority = new ProvenanceAuthority($health);

        // A throwing health reporter must be contained; the authority stays usable.
        $authority->reportUnverified(new RuntimeException('unverified'), 'redacted');

        $id = $authority->issueExportId('execution', 'execution', 'exec', 'exec');
        self::assertTrue($authority->verifyExportId($id, 'execution', 'exec'));
    }

    public function testReportUnverifiedWithoutReporterDoesNotThrow(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->reportUnverified(new RuntimeException('unverified'), 'redacted');
        $authority->reportUnverified(new RuntimeException('still unverified'), 'redacted');
    }

    /**
     * Recursively inspects exception trace arguments for secret values.
     * SensitiveParameterValue instances are treated as redacted and skipped.
     *
     * @param array<array-key, mixed> $trace
     */
    private function assertTraceContainsNoSecrets(array $trace, string ...$needles): void
    {
        foreach ($trace as $frame) {
            if (!is_array($frame)) {
                continue;
            }

            $args = $frame['args'] ?? [];
            if (!is_array($args)) {
                continue;
            }

            foreach ($args as $arg) {
                if ($arg instanceof \SensitiveParameterValue) {
                    continue;
                }

                if (is_array($arg)) {
                    $this->assertTraceContainsNoSecrets([['args' => $arg]], ...$needles);
                } elseif (is_scalar($arg) || $arg === null) {
                    $value = var_export($arg, true);
                    foreach ($needles as $needle) {
                        self::assertStringNotContainsString($needle, $value);
                    }
                }
            }
        }
    }
}
