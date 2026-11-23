<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ObservationSeverityPredicate;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\TrustedArtifactProjectionInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ObservationSeverityPredicate::class)]
final class ObservationSeverityPredicateTest extends Unit
{
    #[DataProvider('severityCases')]
    public function testSeverityFloorDecidesArtifact(
        ExecutionArtifactInterface $artifact,
        int $minLevel,
        bool $expected,
    ): void {
        $predicate = new ObservationSeverityPredicate($minLevel);

        self::assertSame($expected, $predicate->test($artifact));
    }

    /** @return iterable<string, array{0: ExecutionArtifactInterface, 1: int, 2: bool}> */
    public static function severityCases(): iterable
    {
        yield 'plain observation at floor passes' => [
            Observation::warning(
                ObservationId::fromString('obs-1'),
                ExecutionId::fromString('exec-1'),
                'warning message'
            ),
            ObservationSeverity::Warning->level(),
            true,
        ];

        yield 'plain observation below floor fails' => [
            Observation::info(
                ObservationId::fromString('obs-1'),
                ExecutionId::fromString('exec-1'),
                'info message'
            ),
            ObservationSeverity::Error->level(),
            false,
        ];

        yield 'trusted projection observation at floor passes' => [
            new TrustedTestProjection('observation', ['severity' => 3]),
            2,
            true,
        ];

        yield 'trusted projection observation below floor fails' => [
            new TrustedTestProjection('observation', ['level' => 2, 'severity' => 'info']),
            ObservationSeverity::Critical->level(),
            false,
        ];

        yield 'trusted projection uses level over severity' => [
            new TrustedTestProjection('observation', ['level' => 3, 'severity' => 'notice']),
            2,
            true,
        ];

        yield 'redacted observation at floor passes' => [
            new RedactedArtifact(['level' => 3], 'observation'),
            3,
            true,
        ];

        yield 'redacted observation below floor fails' => [
            new RedactedArtifact(['level' => 2], 'observation'),
            ObservationSeverity::Critical->level(),
            false,
        ];
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

    public function testTrustedProjectionNonObservationPasses(): void
    {
        $predicate = new ObservationSeverityPredicate(ObservationSeverity::Error->level());

        self::assertTrue(
            $predicate->test(new TrustedTestProjection('execution', []))
        );
    }

    public function testRedactedArtifactNonObservationPasses(): void
    {
        $predicate = new ObservationSeverityPredicate(ObservationSeverity::Emergency->level());

        self::assertTrue(
            $predicate->test(new RedactedArtifact([], 'execution'))
        );
    }
}

final readonly class TrustedTestProjection implements TrustedArtifactProjectionInterface
{
    /**
     * @param array<string, mixed> $normalized
     */
    public function __construct(
        private string $artifactType,
        private array $normalized,
    ) {
    }

    public function artifactType(): string
    {
        return $this->artifactType;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array
    {
        return $this->normalized;
    }
}
