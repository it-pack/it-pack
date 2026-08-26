<?php

declare(strict_types=1);

namespace Tests\Conformance;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\AlwaysSamplePolicy;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\SamplingPolicyVerifier;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use RuntimeException;
use Tests\Support\Fixture\Conformance\ConformanceViolations;
use Tests\Support\Fixture\Conformance\ImpureNormalizeArtifact;
use Tests\Support\Fixture\Conformance\OverridingPortFixture;

/**
 * Feeds the {@see SamplingPolicyVerifier} broken sampling policies and asserts
 * each contract breach is reported with the expected violation identity.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class SamplingPolicyVerifierNegativeTest extends Unit
{
    private RunItPortFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new RunItPortFixture();
    }

    public function testThrowingSampleIsReportedAndStopsTheCheck(): void
    {
        $policy = new class () implements SamplingPolicyInterface {
            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                throw new RuntimeException('sampling unavailable');
            }
        };

        $result = new SamplingPolicyVerifier()->verify($policy, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['sample.throws'], ConformanceViolations::ids($result));
        self::assertSame(1, count($result));
    }

    public function testAlternatingDecisionsAreReportedForEveryArtifact(): void
    {
        $policy = new class () implements SamplingPolicyInterface {
            private bool $flip = false;

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->flip = !$this->flip;

                return $this->flip;
            }
        };

        $result = new SamplingPolicyVerifier()->verify($policy, $this->fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(
            array_fill(0, 4, 'sample.not-deterministic'),
            ConformanceViolations::ids($result),
        );
    }

    public function testMutatedArtifactIsReported(): void
    {
        $fixture = new OverridingPortFixture(
            $this->fixture,
            samplingArtifacts: [new ImpureNormalizeArtifact()],
        );

        $result = new SamplingPolicyVerifier()->verify(new AlwaysSamplePolicy(), $fixture);

        self::assertFalse($result->isConformant());
        self::assertSame(['sample.mutates'], ConformanceViolations::ids($result));
    }
}
