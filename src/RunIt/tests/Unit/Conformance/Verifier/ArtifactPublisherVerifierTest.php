<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\ArtifactPublisherVerifier;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use RuntimeException;
use Tests\Support\Fixture\RecordingArtifactPublisher;
use Tests\Support\Fixture\ThrowingArtifactPublisher;

/**
 * @group conformance
 */
final class ArtifactPublisherVerifierTest extends Unit
{
    public function testPortReportsArtifactPublisherInterface(): void
    {
        self::assertSame(ArtifactPublisherInterface::class, new ArtifactPublisherVerifier()->port());
    }

    public function testConformantPublisherPasses(): void
    {
        $result = new ArtifactPublisherVerifier()->verify(new RecordingArtifactPublisher(), new RunItPortFixture());

        self::assertTrue($result->isConformant());
        self::assertCount(0, $result);
    }

    public function testPublishThrowIsRecordedAndStops(): void
    {
        $result = new ArtifactPublisherVerifier()->verify(new ThrowingArtifactPublisher(), new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertCount(1, $result->errors());
        self::assertSame('publish.throws', $result->errors()[0]->id);
    }

    public function testFlushThrowIsRecordedAsWarning(): void
    {
        $publisher = new class () implements ArtifactPublisherInterface, FlushablePublisherInterface {
            public function publish(ExecutionArtifactInterface $artifact): void
            {
            }

            public function flush(): void
            {
                throw new RuntimeException('flush broken');
            }
        };

        $result = new ArtifactPublisherVerifier()->verify($publisher, new RunItPortFixture());

        self::assertTrue($result->isConformant());
        self::assertCount(1, $result->warnings());
        self::assertSame('flush.throws', $result->warnings()[0]->id);
    }

    public function testCloseThrowIsRecordedAsWarning(): void
    {
        $publisher = new class () implements ArtifactPublisherInterface, CloseablePublisherInterface {
            public function publish(ExecutionArtifactInterface $artifact): void
            {
            }

            public function close(): void
            {
                throw new RuntimeException('close broken');
            }
        };

        $result = new ArtifactPublisherVerifier()->verify($publisher, new RunItPortFixture());

        self::assertTrue($result->isConformant());
        self::assertCount(1, $result->warnings());
        self::assertSame('close.throws', $result->warnings()[0]->id);
    }
}
