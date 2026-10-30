<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Fixture\ReferenceRuntimeFixture;
use ItPack\AsyncIt\Conformance\Verifier\TraceParentageVerifier;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
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
            public function current(): ?\ItPack\RunIt\Contract\Application\ExecutionScopeInterface
            {
                return null;
            }

            public function attach(\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): \ItPack\RunIt\Contract\Port\ContextTokenInterface
            {
                return new class () implements \ItPack\RunIt\Contract\Port\ContextTokenInterface {
                    public function id(): int
                    {
                        return 0;
                    }
                };
            }

            public function detach(\ItPack\RunIt\Contract\Port\ContextTokenInterface $token): void
            {
            }
        };

        $fixture = new FakeRuntimeFixture(
            $storage,
            0,
        );

        $result = $verifier->verify($fixture);

        self::assertCount(1, $result->warnings());
        self::assertSame('fixture-unsupported', $result->warnings()[0]->id);
    }
}
