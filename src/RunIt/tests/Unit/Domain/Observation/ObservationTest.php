<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Observation;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;

final class ObservationTest extends Unit
{
    public function testSeverityLevelsAndErrorFlag(): void
    {
        self::assertSame(200, ObservationSeverity::Info->level());
        self::assertFalse(ObservationSeverity::Info->isError());
        self::assertTrue(ObservationSeverity::Error->isError());
        self::assertTrue(ObservationSeverity::Critical->isError());
    }

    public function testSeverityNamedConstructorsSetLevel(): void
    {
        $observation = Observation::error(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'boom happened',
            null,
            'E_CFG',
        );

        self::assertSame(ObservationSeverity::Error, $observation->severity());
        self::assertSame('E_CFG', $observation->diagnosticCode());
        self::assertSame('boom happened', $observation->message());
        self::assertTrue($observation->severity()->isError());
    }

    public function testThrowableFreeSerialization(): void
    {
        $observation = Observation::critical(
            ObservationId::fromString('obs-2'),
            ExecutionId::fromString('exec-1'),
            'failure',
            null,
            null,
            null,
            ThrowableDescription::create('RuntimeException', 'boom', 0, null, null),
        );

        $normalized = $observation->normalize();

        self::assertSame('RuntimeException', ((array) $normalized['throwable'])['class']);
        // the original throwable is never embedded
        self::assertStringNotContainsString('boom', serialize($observation->id()));
    }

    public function testActivityBoundObservationReportsActivityId(): void
    {
        $observation = Observation::info(
            ObservationId::fromString('obs-3'),
            ExecutionId::fromString('exec-1'),
            'started',
            ActivityId::fromString('act-1'),
        );

        self::assertSame('act-1', $observation->activityId()?->toString());
    }

    public function testAttributesAreCaptured(): void
    {
        $observation = Observation::warning(
            ObservationId::fromString('obs-4'),
            ExecutionId::fromString('exec-1'),
            'slow',
            null,
            null,
            AttributeCollection::empty()->with('latency_ms', 1500),
        );

        self::assertSame(['latency_ms' => 1500], $observation->attributes());
    }

    public function testNormalizationIsDeterministic(): void
    {
        $observation = Observation::notice(
            ObservationId::fromString('obs-5'),
            ExecutionId::fromString('exec-1'),
            'cached',
            null,
            null,
            null,
            null,
            Instant::of(1000),
        );

        $expected = [
            'id' => 'obs-5',
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'severity' => 'notice',
            'level' => 250,
            'message' => 'cached',
            'diagnostic_code' => null,
            'sequence' => 0,
            'timestamp' => 1000,
            'attributes' => [],
            'throwable' => null,
        ];

        self::assertSame($expected, $observation->normalize());
    }
}
