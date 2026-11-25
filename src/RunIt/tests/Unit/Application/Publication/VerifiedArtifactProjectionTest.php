<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\VerifiedArtifactProjection;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(VerifiedArtifactProjection::class)]
final class VerifiedArtifactProjectionTest extends Unit
{
    public function testPropertiesAreExposed(): void
    {
        $projection = new VerifiedArtifactProjection(['key' => 'value'], 'runit.artifact.execution');

        self::assertSame(['key' => 'value'], $projection->normalize());
        self::assertSame('runit.artifact.execution', $projection->artifactType());
    }
}
