<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ClassifiedTraceObservation;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClassifiedTraceObservation::class)]
final class ClassifiedTraceObservationTest extends Unit
{
    public function testArtifactTypeReturnsObservation(): void
    {
        $observation = $this->createInfoObservation();
        $classified = new ClassifiedTraceObservation($observation);

        self::assertSame('observation', $classified->artifactType());
    }

    public function testIdDelegatesToObservation(): void
    {
        $observation = $this->createInfoObservation();
        $classified = new ClassifiedTraceObservation($observation);

        self::assertSame('obs-1', $classified->id()->toString());
    }

    public function testExecutionIdDelegatesToObservation(): void
    {
        $observation = $this->createInfoObservation();
        $classified = new ClassifiedTraceObservation($observation);

        self::assertSame('exec-1', $classified->executionId()->toString());
    }

    public function testActivityIdDelegatesToObservation(): void
    {
        $observation = $this->createInfoObservation();
        $classified = new ClassifiedTraceObservation($observation);

        self::assertNull($classified->activityId());
    }

    public function testSeverityIsAlwaysTrace(): void
    {
        $observation = Observation::emergency(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'emergency message'
        );
        $classified = new ClassifiedTraceObservation($observation);

        self::assertSame(ObservationSeverity::Trace, $classified->severity());
    }

    public function testMessageDelegatesToObservation(): void
    {
        $observation = $this->createInfoObservation();
        $classified = new ClassifiedTraceObservation($observation);

        self::assertSame('trace message', $classified->message());
    }

    public function testDiagnosticCodeDelegatesToObservation(): void
    {
        $observation = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'trace message',
            diagnosticCode: 'diag-1'
        );
        $classified = new ClassifiedTraceObservation($observation);

        self::assertSame('diag-1', $classified->diagnosticCode());
    }

    public function testThrowableDescriptionDelegatesToObservation(): void
    {
        $observation = $this->createInfoObservation();
        $classified = new ClassifiedTraceObservation($observation);

        self::assertNull($classified->throwableDescription());
    }

    public function testAttributesDelegateToObservation(): void
    {
        $observation = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'trace message',
            attributes: AttributeCollection::fromArray(['key' => 'val'])
        );
        $classified = new ClassifiedTraceObservation($observation);

        self::assertSame(['key' => 'val'], $classified->attributes());
    }

    public function testTimestampDelegatesToObservation(): void
    {
        $observation = $this->createInfoObservation();
        $classified = new ClassifiedTraceObservation($observation);

        self::assertSame($observation->timestamp(), $classified->timestamp());
    }

    public function testSequenceDelegatesToObservation(): void
    {
        $observation = $this->createInfoObservation();
        $classified = new ClassifiedTraceObservation($observation);

        self::assertSame(0, $classified->sequence());
    }

    public function testNormalizeOverridesSeverityAndLevelToTrace(): void
    {
        $observation = Observation::emergency(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'emergency message'
        );
        $classified = new ClassifiedTraceObservation($observation);

        $normalized = $classified->normalize();
        self::assertSame(ObservationSeverity::Trace->value, $normalized['severity']);
        self::assertSame(ObservationSeverity::Trace->level(), $normalized['level']);
    }

    public function testIsInstanceOfObservationInterface(): void
    {
        $observation = $this->createInfoObservation();
        $classified = new ClassifiedTraceObservation($observation);

        self::assertInstanceOf(ObservationInterface::class, $classified);
    }

    public function testIsInstanceOfRedactedArtifactInterface(): void
    {
        $observation = $this->createInfoObservation();
        $classified = new ClassifiedTraceObservation($observation);

        self::assertInstanceOf(RedactedArtifactInterface::class, $classified);
    }

    private function createInfoObservation(): Observation
    {
        return Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'trace message'
        );
    }
}
