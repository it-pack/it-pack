<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Factory\RedactedArtifactFactory;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Domain\Artifact\ArtifactType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(RedactedArtifactFactory::class)]
#[UsesClass(RedactedArtifact::class)]
#[UsesClass(ArtifactType::class)]
class RedactedArtifactFactoryTest extends Unit
{
    public function testCreateWithDefault(): void
    {
        $factory = new RedactedArtifactFactory(ArtifactType::Occurrence);

        $artifact = $factory->create(
            [
                'name' => 'test',
                'description' => 'test description',
                'extension_attributes' => [],
                'sequence' => 1,
            ],
        );

        self::assertSame(ArtifactType::Occurrence->value, $artifact->artifactType());
    }

    public function testCreateWithEnum(): void
    {
        $factory = new RedactedArtifactFactory();

        $artifact = $factory->create(
            [
                'name' => 'test',
                'description' => 'test description',
                'extension_attributes' => [],
                'sequence' => 1,
            ],
            ArtifactType::Occurrence
        );

        self::assertSame(ArtifactType::Occurrence->value, $artifact->artifactType());
    }

    public function testCreateWithString(): void
    {
        $factory = new RedactedArtifactFactory();

        $artifact = $factory->create(
            [
                'name' => 'test',
                'description' => 'test description',
                'extension_attributes' => [],
                'sequence' => 1,
            ],
            ArtifactType::Occurrence->value
        );

        self::assertSame(ArtifactType::Occurrence->value, $artifact->artifactType());
    }

    public function testCreateWithNull(): void
    {
        $factory = new RedactedArtifactFactory();

        $artifact = $factory->create(
            [
                'name' => 'test',
                'description' => 'test description',
                'extension_attributes' => [],
                'sequence' => 1,
            ],
        );

        self::assertSame(ArtifactType::UNKNOWN, $artifact->artifactType());
    }

    public function testWithArtifactType(): void
    {
        $factory = new RedactedArtifactFactory(ArtifactType::Occurrence);

        $artifact = $factory->create(
            [
                'name' => 'test',
                'description' => 'test description',
                'extension_attributes' => [],
                'sequence' => 1,
            ],
        );

        self::assertSame(ArtifactType::Occurrence->value, $artifact->artifactType());

        $factory2 = $factory->withArtifactType(ArtifactType::Activity);

        self::assertNotSame($factory, $factory2);

        $artifact2 = $factory2->create(
            [
                'name' => 'test2',
                'description' => 'test description2',
                'extension_attributes' => [],
                'sequence' => 2,
            ],
        );

        self::assertSame(ArtifactType::Activity->value, $artifact2->artifactType());
    }
}
