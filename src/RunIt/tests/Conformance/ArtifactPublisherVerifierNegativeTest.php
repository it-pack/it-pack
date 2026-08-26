<?php

declare(strict_types=1);

namespace Tests\Conformance;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Result\ViolationSeverity;
use ItPack\RunIt\Conformance\Verifier\ArtifactPublisherVerifier;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use RuntimeException;
use Tests\Support\Fixture\Conformance\ConformanceViolations;
use Tests\Support\Fixture\Conformance\ImpureNormalizeObservation;
use Tests\Support\Fixture\Conformance\OverridingPortFixture;
use Tests\Support\Fixture\ThrowingArtifactPublisher;

/**
 * Feeds the {@see ArtifactPublisherVerifier} broken publisher implementations and
 * asserts each contract breach is reported with the expected violation identity.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ArtifactPublisherVerifierNegativeTest extends Unit
{
    private RunItPortFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new RunItPortFixture();
    }

    public function testThrowingPublishIsReportedAsError(): void
    {
        $result = new ArtifactPublisherVerifier()->verify(new ThrowingArtifactPublisher(), $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['publish.throws'], ConformanceViolations::ids($result));
        self::assertSame(ViolationSeverity::Error, $result->violations[0]->severity);
    }

    public function testMutatedArtifactIsReportedAsError(): void
    {
        $fixture = new OverridingPortFixture(
            $this->fixture,
            safeObservation: new ImpureNormalizeObservation($this->fixture->safeObservation()),
        );

        $result = new ArtifactPublisherVerifier()->verify(new InMemoryArtifactPublisher(), $fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['publish.mutates-artifact'], ConformanceViolations::ids($result));
    }

    public function testThrowingFlushIsReportedAsWarningAndStaysConformant(): void
    {
        $publisher = new class () implements
            ArtifactPublisherInterface,
            FlushablePublisherInterface,
            CloseablePublisherInterface {
            public function publish(ExecutionArtifactInterface $artifact): void
            {
            }

            public function flush(): void
            {
                throw new RuntimeException('flush unavailable');
            }

            public function close(): void
            {
            }
        };

        $result = new ArtifactPublisherVerifier()->verify($publisher, $this->fixture);

        self::assertTrue($result->isConformant(), 'a warning must not fail conformance');
        self::assertSame(1, count($result));
        self::assertSame('flush.throws', $result->violations[0]->id);
        self::assertSame(ViolationSeverity::Warning, $result->violations[0]->severity);
    }

    public function testThrowingCloseIsReportedAsWarningAndStaysConformant(): void
    {
        $publisher = new class () implements
            ArtifactPublisherInterface,
            FlushablePublisherInterface,
            CloseablePublisherInterface {
            public function publish(ExecutionArtifactInterface $artifact): void
            {
            }

            public function flush(): void
            {
            }

            public function close(): void
            {
                throw new RuntimeException('close unavailable');
            }
        };

        $result = new ArtifactPublisherVerifier()->verify($publisher, $this->fixture);

        self::assertTrue($result->isConformant());
        self::assertSame(1, count($result));
        self::assertSame('close.throws', $result->violations[0]->id);
        self::assertSame(ViolationSeverity::Warning, $result->violations[0]->severity);
    }

    public function testThrowingFlushAndCloseAreBothReported(): void
    {
        $publisher = new class () implements
            ArtifactPublisherInterface,
            FlushablePublisherInterface,
            CloseablePublisherInterface {
            public function publish(ExecutionArtifactInterface $artifact): void
            {
            }

            public function flush(): void
            {
                throw new RuntimeException('flush unavailable');
            }

            public function close(): void
            {
                throw new RuntimeException('close unavailable');
            }
        };

        $result = new ArtifactPublisherVerifier()->verify($publisher, $this->fixture);

        self::assertTrue($result->isConformant());
        self::assertSame(['flush.throws', 'close.throws'], ConformanceViolations::ids($result));
    }
}
