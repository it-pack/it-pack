<?php

declare(strict_types=1);

namespace Tests\Unit\SDK;

use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\ArtifactPublisherVerifier;
use ItPack\RunIt\Conformance\Verifier\ContextStorageVerifier;
use ItPack\RunIt\Conformance\Verifier\MonotonicClockVerifier;
use ItPack\RunIt\Contract\Application\EngineCapability;
use PHPUnit\Framework\TestCase;

/**
 * Reuses RunIt's first-party verifiers against the adapters the reference
 * runtime is built from: the system monotonic clock, the LIFO context storage,
 * and the fail-safe (recursion-guarded, health-reported) artifact publisher.
 *
 * @covers \ItPack\AsyncIt\Application\Context\MemoryContextStorage
 * @covers \ItPack\RunIt\Adapter\Common\SystemMonotonicClock
 * @covers \ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher
 */
final class RunItAdapterConformanceTest extends TestCase
{
    public function testSystemMonotonicClockConforms(): void
    {
        $result = new MonotonicClockVerifier()->verify(new SystemMonotonicClock());

        self::assertTrue($result->isConformant(), $result->summary());
    }

    public function testMemoryContextStorageConforms(): void
    {
        $result = new ContextStorageVerifier()->verify(
            new MemoryContextStorage(),
            new ReferenceExecutionScope('ctx'),
        );

        self::assertTrue($result->isConformant(), $result->summary());
    }

    public function testFailSafeArtifactPublisherConforms(): void
    {
        $health = new CallbackHealthReporter(static function (): void {
        });
        $publisher = new FailSafeArtifactPublisher(
            new InMemoryArtifactPublisher(),
            new FailOpenPolicy(),
            $health,
        );

        $result = new ArtifactPublisherVerifier()->verify($publisher, new RunItPortFixture());

        self::assertTrue($result->isConformant(), $result->summary());
    }

    public function testDiscoveredRuntimeUsesRunItAdaptersEndToEnd(): void
    {
        $runtime = AsyncRuntime::discover();

        $value = $runtime->run(new ReferenceExecutionScope('e'), static fn (): int => 99);

        self::assertSame(99, $value);
        $capability = $runtime->capability();
        self::assertInstanceOf(EngineCapability::class, $capability);
        self::assertTrue($capability->supportsFibers);
    }
}
