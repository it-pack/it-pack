<?php

declare(strict_types=1);

namespace Tests\Conformance;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Adapter\Memory\InMemorySnapshotStore;
use ItPack\RunIt\Application\Engine\SynchronousExecutionEngine;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Application\Publication\AlwaysSamplePolicy;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\NeverSamplePolicy;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\ArtifactPublisherVerifier;
use ItPack\RunIt\Conformance\Verifier\ContextStorageVerifier;
use ItPack\RunIt\Conformance\Verifier\ExecutionEngineVerifier;
use ItPack\RunIt\Conformance\Verifier\IdentifierGeneratorVerifier;
use ItPack\RunIt\Conformance\Verifier\MonotonicClockVerifier;
use ItPack\RunIt\Conformance\Verifier\RedactorVerifier;
use ItPack\RunIt\Conformance\Verifier\SamplingPolicyVerifier;
use ItPack\RunIt\Conformance\Verifier\SerializerVerifier;
use ItPack\RunIt\Conformance\Verifier\SnapshotStoreVerifier;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\Instant;
use Tests\Support\Fixture\RunItTestKit;

/**
 * Exercises the framework-agnostic port conformance verifiers against the bundled
 * RunIt adapters. A third-party adapter author reuses the same verifiers with their
 * own implementation and {@see RunItPortFixture}-equivalent fixture material.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RunItPortConformanceTest extends Unit
{
    private RunItPortFixture $fixture;

    private RunItTestKit $kit;

    protected function setUp(): void
    {
        $this->fixture = new RunItPortFixture();
        $this->kit = new RunItTestKit();
    }

    private function scope(string $id): ExecutionScopeInterface
    {
        return $this->kit->buildScope(
            Execution::start(
                ExecutionId::fromString($id),
                ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
                Instant::of(1),
            ),
            ExecutionContext::empty(),
        );
    }

    public function testArtifactPublisherConforms(): void
    {
        $result = new ArtifactPublisherVerifier()->verify(new InMemoryArtifactPublisher(), $this->fixture);

        self::assertTrue($result->isConformant(), $this->describe($result));
    }

    public function testContextStorageConforms(): void
    {
        $result = new ContextStorageVerifier()->verify(new StackContextStorage(), $this->scope('exec-1'));

        self::assertTrue($result->isConformant(), $this->describe($result));
    }

    public function testIdentifierGeneratorConforms(): void
    {
        $result = new IdentifierGeneratorVerifier()->verify(new RandomIdentifierGenerator(), $this->fixture);

        self::assertTrue($result->isConformant(), $this->describe($result));
    }

    public function testMonotonicClockConforms(): void
    {
        $result = new MonotonicClockVerifier()->verify(new SystemMonotonicClock(), $this->fixture);

        self::assertTrue($result->isConformant(), $this->describe($result));
    }

    public function testSerializerConforms(): void
    {
        $result = new SerializerVerifier()->verify(new JsonSnapshotSerializer(), $this->fixture);

        self::assertTrue($result->isConformant(), $this->describe($result));
    }

    public function testSnapshotStoreConforms(): void
    {
        $result = new SnapshotStoreVerifier()->verify(new InMemorySnapshotStore(), $this->fixture);

        self::assertTrue($result->isConformant(), $this->describe($result));
    }

    public function testRedactorConforms(): void
    {
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(sensitiveAttributeKeys: ['password']));
        $result = new RedactorVerifier()->verify($redactor, $this->fixture);

        self::assertTrue($result->isConformant(), $this->describe($result));
    }

    public function testSamplingPolicyConforms(): void
    {
        $result = new SamplingPolicyVerifier()->verify(new AlwaysSamplePolicy(), $this->fixture);
        self::assertTrue($result->isConformant(), $this->describe($result));

        $result = new SamplingPolicyVerifier()->verify(new NeverSamplePolicy(), $this->fixture);
        self::assertTrue($result->isConformant(), $this->describe($result));
    }

    public function testExecutionEngineConforms(): void
    {
        $result = new ExecutionEngineVerifier()->verify(new SynchronousExecutionEngine(), $this->scope('exec-1'));

        self::assertTrue($result->isConformant(), $this->describe($result));
    }

    private function describe(\ItPack\RunIt\Conformance\Result\ConformanceResult $result): string
    {
        $lines = [$result->summary()];
        foreach ($result->violations as $violation) {
            $lines[] = (string) $violation;
        }

        return implode("\n", $lines);
    }
}
