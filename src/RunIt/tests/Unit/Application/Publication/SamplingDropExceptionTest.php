<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\SamplingDropException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SamplingDropException::class)]
final class SamplingDropExceptionTest extends Unit
{
    public function testForArtifact(): void
    {
        $exception = SamplingDropException::forArtifact();

        self::assertSame('Artifact dropped by sampling policy.', $exception->getMessage());
    }
}
