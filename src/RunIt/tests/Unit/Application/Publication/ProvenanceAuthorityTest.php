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
}
