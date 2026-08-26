<?php

declare(strict_types=1);

namespace Tests\Conformance;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\RedactorVerifier;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use RuntimeException;
use Tests\Support\Fixture\Conformance\ConformanceViolations;
use Tests\Support\Fixture\Conformance\ImpureNormalizeObservation;
use Tests\Support\Fixture\Conformance\OverridingPortFixture;

/**
 * Feeds the {@see RedactorVerifier} broken redactors and asserts each contract
 * breach is reported with the expected violation identity.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RedactorVerifierNegativeTest extends Unit
{
    private RunItPortFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new RunItPortFixture();
    }

    public function testLeavingTheSecretVisibleIsReported(): void
    {
        $result = new RedactorVerifier()->verify($this->passthrough(), $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['redact.secret.leaks'], ConformanceViolations::ids($result));
        self::assertSame(1, count($result));
    }

    public function testThrowingSafeRedactionStopsTheCheck(): void
    {
        $redactor = new class () implements RedactorInterface {
            public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
            {
                if ($artifact instanceof ObservationInterface && $artifact->message() === 'Service started') {
                    throw new RuntimeException('redaction exploded on safe input');
                }

                return $artifact;
            }
        };

        $result = new RedactorVerifier()->verify($redactor, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['redact.safe.throws'], ConformanceViolations::ids($result));
        self::assertSame(1, count($result));
    }

    public function testThrowingSecretRedactionStopsTheCheck(): void
    {
        $redactor = new class () implements RedactorInterface {
            public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
            {
                if ($artifact instanceof ObservationInterface && $artifact->message() === 'Connected to upstream') {
                    throw new RuntimeException('redaction exploded on secret input');
                }

                return $artifact;
            }
        };

        $result = new RedactorVerifier()->verify($redactor, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['redact.secret.throws'], ConformanceViolations::ids($result));
        self::assertSame(1, count($result));
    }

    public function testAlteringASafeArtifactIsReportedAlongsideTheLeak(): void
    {
        $replacement = $this->fixture->occurrence();

        $redactor = new class ($replacement) implements RedactorInterface {
            public function __construct(private readonly ExecutionArtifactInterface $replacement)
            {
            }

            public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
            {
                if ($artifact instanceof ObservationInterface && $artifact->message() === 'Service started') {
                    return $this->replacement;
                }

                return $artifact;
            }
        };

        $result = new RedactorVerifier()->verify($redactor, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['redact.safe.alters', 'redact.secret.leaks'], ConformanceViolations::ids($result));
    }

    public function testMutatedSafeSourceIsReported(): void
    {
        $fixture = new OverridingPortFixture(
            $this->fixture,
            safeObservation: new ImpureNormalizeObservation($this->fixture->safeObservation()),
        );

        $result = new RedactorVerifier()->verify($this->passthrough(), $fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(
            ['redact.safe.alters', 'redact.safe.mutates', 'redact.secret.leaks'],
            ConformanceViolations::ids($result),
        );
    }

    public function testMutatedSensitiveSourceIsReported(): void
    {
        $fixture = new OverridingPortFixture(
            $this->fixture,
            sensitiveObservation: new ImpureNormalizeObservation($this->fixture->sensitiveObservation()),
        );

        $result = new RedactorVerifier()->verify($this->passthrough(), $fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['redact.secret.mutates'], ConformanceViolations::ids($result));
    }

    private function passthrough(): RedactorInterface
    {
        return new class () implements RedactorInterface {
            public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
            {
                return $artifact;
            }
        };
    }
}
