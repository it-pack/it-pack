<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\SamplingPolicyVerifier;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use RuntimeException;

/**
 * @group conformance
 */
final class SamplingPolicyVerifierTest extends Unit
{
    public function testPortReportsSamplingPolicyInterface(): void
    {
        self::assertSame(SamplingPolicyInterface::class, new SamplingPolicyVerifier()->port());
    }

    public function testConformantPolicyPasses(): void
    {
        $policy = new class () implements SamplingPolicyInterface {
            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                return true;
            }
        };

        $result = new SamplingPolicyVerifier()->verify($policy, new RunItPortFixture());

        self::assertTrue($result->isConformant());
        self::assertCount(0, $result);
    }

    public function testSampleThrowIsRecorded(): void
    {
        $policy = new class () implements SamplingPolicyInterface {
            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                throw new RuntimeException('sample broken');
            }
        };

        $result = new SamplingPolicyVerifier()->verify($policy, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertCount(1, $result->errors());
        self::assertSame('sample.throws', $result->errors()[0]->id);
    }

    public function testNotDeterministicIsRecorded(): void
    {
        $policy = new class () implements SamplingPolicyInterface {
            private int $calls = 0;

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->calls++;

                return $this->calls % 2 === 0;
            }
        };

        $result = new SamplingPolicyVerifier()->verify($policy, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertCount(4, $result->errors());
        self::assertSame('sample.not-deterministic', $result->errors()[0]->id);
    }
}
