<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Fixture\ReferenceRuntimeFixture;
use ItPack\AsyncIt\Conformance\Verifier\TraceParentageVerifier;
use PHPUnit\Framework\TestCase;

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
}
