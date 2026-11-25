<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\PayloadSectionByteEstimator;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PayloadSectionByteEstimator::class)]
final class PayloadSectionByteEstimatorTest extends Unit
{
    public function testEstimateReturnsZeroForNonOccurrenceSnapshot(): void
    {
        $estimator = new PayloadSectionByteEstimator();
        $artifact = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [];
            }
        };

        self::assertSame(0, $estimator->estimate($artifact));
    }

    public function testDeeplyNestedArrayCountsAsOpaqueValue(): void
    {
        $limits = SecurityLimits::default()->withMaxSerializationDepth(1);
        $estimator = new PayloadSectionByteEstimator($limits);
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-1'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.test.v1'),
            'source',
            Instant::of(1000),
            ['a' => ['b' => 'deep']],
        );

        self::assertSame(65, $estimator->estimate($occurrence));
    }
}
