<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Fixture\ReferenceRuntimeFixture;
use ItPack\AsyncIt\Conformance\Result\ConformanceViolation;
use ItPack\AsyncIt\Conformance\Verifier\TraceParentageVerifier;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Conformance\Fixture\FakeRuntimeFixture;

/**
 * @covers \ItPack\AsyncIt\Conformance\Verifier\TraceParentageVerifier
 */
final class TraceParentageVerifierTest extends TestCase
{
    public function testPassesOnReferenceRuntime(): void
    {
        $verifier = new TraceParentageVerifier();

        $result = $verifier->verify(new ReferenceRuntimeFixture());

        self::assertTrue($result->isConformant(), $result->summary());
    }

    public function testReportsUnsupportedWhenFixtureHasNoTelemetry(): void
    {
        $verifier = new TraceParentageVerifier();

        $storage = new class () implements ContextStorageInterface {
            public function current(): ?ExecutionScopeInterface
            {
                return null;
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                return new class () implements ContextTokenInterface {
                    public function id(): int
                    {
                        return 0;
                    }
                };
            }

            public function detach(ContextTokenInterface $token): void
            {
            }
        };

        $fixture = new FakeRuntimeFixture(
            $storage,
            0,
        );

        $result = $verifier->verify($fixture);

        self::assertCount(1, $result->warnings());
        $violation = $result->warnings()[0];
        self::assertInstanceOf(ConformanceViolation::class, $violation);
        self::assertSame('fixture-unsupported', $violation->id);
    }
}
