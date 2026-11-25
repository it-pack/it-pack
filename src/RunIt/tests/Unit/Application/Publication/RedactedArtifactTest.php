<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Contract\Domain\ArtifactType;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RedactedArtifact::class)]
final class RedactedArtifactTest extends Unit
{
    public function testPropertiesAreExposed(): void
    {
        $artifact = new RedactedArtifact(['key' => 'value'], ArtifactType::EXECUTION);

        self::assertSame(['key' => 'value'], $artifact->normalize());
        self::assertSame('execution', $artifact->artifactType());
    }

    public function testStringArtifactTypeIsStored(): void
    {
        $artifact = new RedactedArtifact([], 'custom.type');

        self::assertSame('custom.type', $artifact->artifactType());
    }
}
