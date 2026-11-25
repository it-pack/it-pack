<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ClassifiedRedactedOccurrence;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClassifiedRedactedOccurrence::class)]
final class ClassifiedRedactedOccurrenceTest extends Unit
{
    public function testImplementsRedactedArtifactInterface(): void
    {
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-1'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.test.v1'),
            'source',
            Instant::of(1000),
            [],
            null,
            null,
            null,
            'application/json',
            AttributeCollection::empty(),
            ArtifactSequence::initial(),
        );

        $classified = new ClassifiedRedactedOccurrence($occurrence);

        self::assertSame('occ-1', $classified->id()->toString());
        self::assertSame('exec-1', $classified->executionId()->toString());
        self::assertSame('itpack.test.v1', $classified->type()->toString());
    }

    public function testDelegatesAllMethodsToInnerOccurrence(): void
    {
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-1'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.test.v1'),
            'source',
            Instant::of(1000),
            ['key' => 'value'],
            null,
            'subject',
            'schema-1',
            'application/json',
            AttributeCollection::empty()->with('attr', 1),
            ArtifactSequence::initial(),
        );

        $classified = new ClassifiedRedactedOccurrence($occurrence);

        self::assertSame('occ-1', $classified->id()->toString());
        self::assertSame('exec-1', $classified->executionId()->toString());
        self::assertNull($classified->activityId());
        self::assertSame('itpack.test.v1', $classified->type()->toString());
        self::assertSame('source', $classified->source());
        self::assertSame('subject', $classified->subject());
        self::assertSame(1000, $classified->timestamp());
        self::assertSame(['key' => 'value'], $classified->data());
        self::assertSame('schema-1', $classified->dataSchema());
        self::assertSame('application/json', $classified->contentType());
        self::assertSame(['attr' => 1], $classified->extensionAttributes());
        self::assertSame(0, $classified->sequence());
        self::assertSame($occurrence->normalize(), $classified->normalize());
        self::assertSame('occurrence', $classified->artifactType());
    }

    public function testWithActivityId(): void
    {
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-2'),
            ExecutionId::fromString('exec-2'),
            OccurrenceType::fromString('itpack.test.v2'),
            'source-2',
            Instant::of(2000),
            [],
            null,
            null,
            null,
            'application/json',
            AttributeCollection::empty(),
            ArtifactSequence::initial(),
        );

        $classified = new ClassifiedRedactedOccurrence($occurrence);

        self::assertNull($classified->activityId());
    }
}
