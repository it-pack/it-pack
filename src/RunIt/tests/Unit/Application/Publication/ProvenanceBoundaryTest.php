<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\ProvenanceArtifactPublisher;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\Fixture\RunItTestKit;
use Throwable;

#[CoversClass(ProvenanceArtifactPublisher::class)]
#[CoversClass(RedactingArtifactPublisher::class)]
final class ProvenanceBoundaryTest extends Unit
{
    public function testRawArtifactDeferredBeyondExecutionCleanupIsDroppedAndReported(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(
            static function (Throwable $failure, ExecutionArtifactInterface $artifact) use (&$reports): void {
                $reports[] = [$failure->getMessage(), $artifact->normalize()];
            },
        );
        $authority = new ProvenanceAuthority($health);
        $deferred = null;
        $capture = self::createStub(ArtifactPublisherInterface::class);
        $capture->method('publish')->willReturnCallback(
            static function (ExecutionArtifactInterface $artifact) use (&$deferred, $authority): void {
                if ($artifact instanceof ExecutionInterface) {
                    self::assertNotNull($authority->exportIdForArtifact($artifact));
                    $deferred = $artifact;
                }
            },
        );
        $kit = new RunItTestKit(publisher: $capture, authority: $authority);
        $definition = ExecutionDefinition::create(ExecutionName::fromString('test'), ExecutionKind::fromString('test'));
        $kit->runner->run($definition, static fn (): null => null);

        self::assertInstanceOf(ExecutionInterface::class, $deferred);
        self::assertFalse($authority->hasLiveRecords('exec-1'));
        self::assertNull($authority->exportIdForArtifact($deferred));
        $inner = $this->createMock(ArtifactPublisherInterface::class);
        $inner->expects($this->never())->method('publish');
        new ProvenanceArtifactPublisher($authority, $inner)->publish($deferred);

        self::assertCount(1, $reports);
        self::assertSame('Artifact provenance verification failed.', $reports[0][0]);
        self::assertSame(['id' => 'redacted', 'type' => 'runit.artifact.redacted'], $reports[0][1]);
        self::assertStringNotContainsString('exec-1', serialize($reports));
    }

    public function testForgedCopyNeverReachesConfiguredStack(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(
            static function (
                Throwable $failure,
                ExecutionArtifactInterface $artifact,
            ) use (&$reports): void {
                $reports[] = [$failure->getMessage(), $artifact->normalize()];
            },
        );
        $authority = new ProvenanceAuthority($health);
        $id = $authority->issueExportId('observation', 'observation', 'secret-execution', 'secret-id');
        $issued = new RedactedArtifact(['id' => 'secret-id', 'execution_id' => 'secret-execution']);
        $authority->attest($issued, $id);
        $copy = new RedactedArtifact($issued->normalize());
        $inner = $this->createMock(ArtifactPublisherInterface::class);
        $inner->expects($this->once())->method('publish')->with($issued);
        $publisher = new ProvenanceArtifactPublisher($authority, $inner);

        $publisher->publish($copy);
        $publisher->publish($issued);

        self::assertCount(1, $reports);
        self::assertStringNotContainsString('secret', serialize($reports));
    }

    public function testRedactorCannotChangeRelationshipBeforeSampler(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(
            static function (
                Throwable $failure,
                ExecutionArtifactInterface $artifact,
            ) use (&$reports): void {
                $reports[] = [$failure->getMessage(), $artifact->normalize()];
            },
        );
        $authority = new ProvenanceAuthority($health);
        $id = $authority->issueExportId('observation', 'observation', 'exec', 'obs');
        $artifact = $this->createMock(ObservationInterface::class);
        $artifact->method('normalize')->willReturn([
            'id' => 'obs', 'execution_id' => 'exec', 'activity_id' => 'original',
        ]);
        $authority->attest($artifact, $id);
        $redactor = $this->createMock(RedactorInterface::class);
        $redactor->method('redact')->willReturn(new RedactedArtifact([
            'id' => 'obs', 'execution_id' => 'exec', 'activity_id' => 'secret-forgery',
        ], 'observation'));
        $inner = $this->createMock(ArtifactPublisherInterface::class);
        $inner->expects($this->never())->method('publish');

        new RedactingArtifactPublisher($redactor, $inner, authority: $authority)->publish($artifact);

        self::assertCount(1, $reports);
        self::assertStringNotContainsString('secret-forgery', serialize($reports));
    }

    public function testVerifiedRedactionFreezesProjection(): void
    {
        $authority = new ProvenanceAuthority();
        $root = $authority->issueExportId('execution', 'execution', 'exec', 'exec');
        $id = $authority->bindArtifact('observation', 'observation', 'exec', 'obs', 'execution-observation');
        $artifact = $this->createMock(ObservationInterface::class);
        $artifact->method('normalize')->willReturn(['id' => 'obs', 'execution_id' => 'exec', 'message' => 'secret']);
        $authority->attest($artifact, $id);
        $redactor = $this->createMock(RedactorInterface::class);
        $redactor->method('redact')->willReturn(new RedactedArtifact([
            'id' => 'obs', 'execution_id' => 'exec', 'message' => '[redacted]',
        ], 'observation'));
        $inner = $this->createMock(ArtifactPublisherInterface::class);
        $inner->expects($this->once())->method('publish')->with(self::callback(
            static fn (ExecutionArtifactInterface $projection): bool =>
                $projection->normalize()['message'] === '[redacted]'
                && $projection->normalize()['id'] === $id
                && $projection->normalize()['execution_id'] === $root,
        ));

        new RedactingArtifactPublisher($redactor, $inner, authority: $authority)->publish($artifact);
    }

    public function testObservationDropUsesRedactorResultNotIdentityProjection(): void
    {
        foreach (['none', 'same', 'redacted'] as $mode) {
            $authority = new ProvenanceAuthority();
            $root = $authority->issueExportId('execution', 'execution', 'exec', 'exec');
            $id = $authority->bindArtifact('observation', 'observation', 'exec', 'obs', 'execution-observation');
            $artifact = self::createStub(ObservationInterface::class);
            $fields = ['id' => 'obs', 'execution_id' => 'exec', 'activity_id' => 'secret-activity'];
            $artifact->method('normalize')->willReturn($fields);
            self::assertTrue($authority->attest($artifact, $id));
            $redactor = null;
            if ($mode !== 'none') {
                $redactor = self::createStub(RedactorInterface::class);
                $redactor->method('redact')->willReturn(
                    $mode === 'same' ? $artifact : new RedactedArtifact($fields, 'observation'),
                );
            }

            $inner = $this->createMock(ArtifactPublisherInterface::class);
            $inner->expects($mode === 'redacted' ? $this->never() : $this->once())->method('publish')
                ->with(self::callback(static function (ExecutionArtifactInterface $projection) use ($root, $id): bool {
                    $fields = $projection->normalize();
                    return $fields['id'] === $id && $fields['execution_id'] === $root
                        && $fields['activity_id'] === null;
                }))
            ;
            $reports = [];
            $health = new CallbackHealthReporter(static function (Throwable $error) use (&$reports): void {
                $reports[] = $error;
            });
            new RedactingArtifactPublisher($redactor, $inner, $health, true, $authority)->publish($artifact);
            self::assertCount($mode === 'redacted' ? 1 : 0, $reports);
        }
    }

    public function testCustomRedactorWrapperCannotPublishRawIdentities(): void
    {
        $authority = new ProvenanceAuthority();
        $root = $authority->issueExportId('execution', 'execution', 'secret-exec', 'secret-exec');
        $id = $authority->bindArtifact(
            'observation',
            'observation',
            'secret-exec',
            'secret-obs',
            'execution-observation',
        );
        $fields = ['id' => 'secret-obs', 'execution_id' => 'secret-exec', 'activity_id' => 'secret-activity'];
        $original = self::createStub(ObservationInterface::class);
        $original->method('normalize')->willReturn($fields);
        self::assertTrue($authority->attest($original, $id));
        $wrapper = self::createStub(ObservationInterface::class);
        $wrapper->method('normalize')->willReturn($fields);
        $redactor = self::createStub(RedactorInterface::class);
        $redactor->method('redact')->willReturn($wrapper);
        $inner = $this->createMock(ArtifactPublisherInterface::class);
        $inner->expects($this->once())->method('publish')->with(self::callback(
            static fn (ExecutionArtifactInterface $result): bool =>
                $result->normalize()['id'] === $id && $result->normalize()['execution_id'] === $root
                && $result->normalize()['activity_id'] === null
                && !str_contains(serialize($result->normalize()), 'secret'),
        ));
        new RedactingArtifactPublisher($redactor, $inner, authority: $authority)->publish($original);
    }
}
