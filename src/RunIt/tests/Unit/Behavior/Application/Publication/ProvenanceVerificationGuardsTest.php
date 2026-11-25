<?php

declare(strict_types=1);

namespace Tests\Unit\Behavior\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Behavior\Application\Publication\ProvenanceVerificationGuards;
use PHPUnit\Framework\Attributes\CoversTrait;
use RuntimeException;

/**
 * Verifies the shared provenance verification guard through an anonymous class:
 * safe verification returns false on throwing steps.
 *
 * @group behavior
 * @group publication
 */
#[CoversTrait(ProvenanceVerificationGuards::class)]
final class ProvenanceVerificationGuardsTest extends Unit
{
    public function testSafeVerifyReturnsTrueOnSuccess(): void
    {
        $fixture = new class () {
            use ProvenanceVerificationGuards;

            public function check(): bool
            {
                return $this->safeVerify(static fn (): bool => true);
            }
        };

        self::assertTrue($fixture->check());
    }

    public function testSafeVerifyReturnsFalseOnThrow(): void
    {
        $fixture = new class () {
            use ProvenanceVerificationGuards;

            public function check(): bool
            {
                return $this->safeVerify(static fn (): bool => throw new RuntimeException('bad'));
            }
        };

        self::assertFalse($fixture->check());
    }
}
