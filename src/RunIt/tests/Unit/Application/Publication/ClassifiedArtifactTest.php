<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ClassifiedArtifact;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClassifiedArtifact::class)]
final class ClassifiedArtifactTest extends Unit
{
    public function testPropertiesAreExposed(): void
    {
        $artifact = new ClassifiedArtifact(['key' => 'value'], 'runit.artifact.execution');

        self::assertSame(['key' => 'value'], $artifact->normalize());
        self::assertSame('runit.artifact.execution', $artifact->artifactType());
    }
}
