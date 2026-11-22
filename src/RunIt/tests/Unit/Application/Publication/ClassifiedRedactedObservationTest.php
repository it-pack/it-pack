<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ClassifiedRedactedObservation;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClassifiedRedactedObservation::class)]
final class ClassifiedRedactedObservationTest extends Unit
{
    public function testArtifactTypeReturnsObservation(): void
    {
        $wrapped = new ClassifiedRedactedObservation($this->observation());

        self::assertSame('observation', $wrapped->artifactType());
    }

    public function testIdentityDelegatesToWrappedObservation(): void
    {
        $source = $this->observation();
        $wrapped = new ClassifiedRedactedObservation($source);

        self::assertSame('obs-1', $wrapped->id()->toString());
        self::assertSame('exec-1', $wrapped->executionId()->toString());
        self::assertNull($wrapped->activityId());
    }

    public function testSeverityAndMessageDelegateToWrappedObservation(): void
    {
        $source = $this->observation();
        $wrapped = new ClassifiedRedactedObservation($source);

        self::assertSame(ObservationSeverity::Warning, $wrapped->severity());
        self::assertSame('classified message', $wrapped->message());
        self::assertSame('diag-1', $wrapped->diagnosticCode());
    }

    public function testAttributesTimestampAndSequenceDelegate(): void
    {
        $source = $this->observation();
        $wrapped = new ClassifiedRedactedObservation($source);

        self::assertSame(['key' => 'value'], $wrapped->attributes());
        self::assertSame(1000, $wrapped->timestamp());
        self::assertSame(5, $wrapped->sequence());
    }

    public function testNormalizeDelegatesToWrappedObservation(): void
    {
        $source = $this->observation();
        $wrapped = new ClassifiedRedactedObservation($source);

        self::assertSame($source->normalize(), $wrapped->normalize());
        self::assertIsArray($wrapped->normalize());
    }

    private function observation(): Observation
    {
        return Observation::warning(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'classified message',
            null,
            'diag-1',
            AttributeCollection::fromArray(['key' => 'value']),
            null,
            Instant::of(1000),
            ArtifactSequence::of(5)
        );
    }
}
