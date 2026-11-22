<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ObservationSeverityPredicate;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\TrustedArtifactProjectionInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ObservationSeverityPredicate::class)]
final class ObservationSeverityPredicateTest extends Unit
{
    public function testObservationAtOrAboveFloorPasses(): void
    {
        $predicate = new ObservationSeverityPredicate(ObservationSeverity::Warning->level());
        $observation = Observation::warning(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'warning message'
        );

        self::assertTrue($predicate->test($observation));
    }

    public function testObservationBelowFloorFails(): void
    {
        $predicate = new ObservationSeverityPredicate(ObservationSeverity::Error->level());
        $observation = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'info message'
        );

        self::assertFalse($predicate->test($observation));
    }

    public function testNonObservationArtifactPasses(): void
    {
        $predicate = new ObservationSeverityPredicate(ObservationSeverity::Error->level());
        $artifact = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [];
            }
        };

        self::assertTrue($predicate->test($artifact));
    }

    public function testTrustedProjectionObservationAtFloorPasses(): void
    {
        $predicate = new ObservationSeverityPredicate(4);
        $artifact = new class () implements ExecutionArtifactInterface, TrustedArtifactProjectionInterface {
            public function artifactType(): string
            {
                return 'observation';
            }

            public function normalize(): array
            {
                return ['level' => 4, 'severity' => 'warning'];
            }
        };

        self::assertTrue($predicate->test($artifact));
    }

    public function testTrustedProjectionObservationBelowFloorFails(): void
    {
        $predicate = new ObservationSeverityPredicate(5);
        $artifact = new class () implements ExecutionArtifactInterface, TrustedArtifactProjectionInterface {
            public function artifactType(): string
            {
                return 'observation';
            }

            public function normalize(): array
            {
                return ['level' => 2, 'severity' => 'info'];
            }
        };

        self::assertFalse($predicate->test($artifact));
    }

    public function testTrustedProjectionNonObservationPasses(): void
    {
        $predicate = new ObservationSeverityPredicate(ObservationSeverity::Error->level());
        $artifact = new class () implements ExecutionArtifactInterface, TrustedArtifactProjectionInterface {
            public function artifactType(): string
            {
                return 'execution';
            }

            public function normalize(): array
            {
                return [];
            }
        };

        self::assertTrue($predicate->test($artifact));
    }

    public function testRedactedArtifactObservationAtFloorPasses(): void
    {
        $predicate = new ObservationSeverityPredicate(3);
        $artifact = new class () implements ExecutionArtifactInterface, RedactedArtifactInterface {
            public function artifactType(): string
            {
                return 'observation';
            }

            public function normalize(): array
            {
                return ['level' => 3, 'severity' => 'notice'];
            }
        };

        self::assertTrue($predicate->test($artifact));
    }

    public function testRedactedArtifactObservationBelowFloorFails(): void
    {
        $predicate = new ObservationSeverityPredicate(ObservationSeverity::Critical->level());
        $artifact = new class () implements ExecutionArtifactInterface, RedactedArtifactInterface {
            public function artifactType(): string
            {
                return 'observation';
            }

            public function normalize(): array
            {
                return ['level' => 2, 'severity' => 'info'];
            }
        };

        self::assertFalse($predicate->test($artifact));
    }

    public function testRedactedArtifactNonObservationPasses(): void
    {
        $predicate = new ObservationSeverityPredicate(ObservationSeverity::Emergency->level());
        $artifact = new class () implements ExecutionArtifactInterface, RedactedArtifactInterface {
            public function artifactType(): string
            {
                return 'execution';
            }

            public function normalize(): array
            {
                return [];
            }
        };

        self::assertTrue($predicate->test($artifact));
    }

    public function testTrustedProjectionUsesLevelFallbackToSeverity(): void
    {
        $predicate = new ObservationSeverityPredicate(2);
        $artifact = new class () implements ExecutionArtifactInterface, TrustedArtifactProjectionInterface {
            public function artifactType(): string
            {
                return 'observation';
            }

            public function normalize(): array
            {
                return ['level' => 3, 'severity' => 'notice'];
            }
        };

        self::assertTrue($predicate->test($artifact));
    }
}
