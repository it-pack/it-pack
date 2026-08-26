<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Result;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Result\ConformanceResult;
use ItPack\RunIt\Conformance\Result\ConformanceViolation;
use ItPack\RunIt\Conformance\Result\ViolationSeverity;

/**
 * @group conformance
 */
final class ConformanceResultTest extends Unit
{
    public function testAddReturnsANewImmutableResultCarryingTheViolation(): void
    {
        $result = new ConformanceResult('ArtifactPublisher');
        $violation = new ConformanceViolation('close-before-flush', 'publisher was closed before flushing');

        $updated = $result->add($violation);

        self::assertNotSame($result, $updated);
        self::assertCount(0, $result);
        self::assertCount(1, $updated);
        self::assertSame($violation, $updated->violations[0]);
    }

    public function testWarningsOnlyResultIsStillConformant(): void
    {
        $result = new ConformanceResult('SnapshotStore')
            ->add(new ConformanceViolation('soft-limit', 'store exceeded the soft limit', ViolationSeverity::Warning))
        ;

        self::assertTrue($result->isConformant());
        self::assertSame([], $result->errors());
        self::assertCount(1, $result->warnings());
    }

    public function testErrorsMakeTheResultNonConformantAndAreListed(): void
    {
        $result = new ConformanceResult('MonotonicClock')
            ->add(new ConformanceViolation('wall-clock', 'clock jumped backwards', ViolationSeverity::Warning))
            ->add(new ConformanceViolation('non-monotonic', 'elapsed time was negative'))
        ;

        self::assertFalse($result->isConformant());
        self::assertCount(1, $result->errors());
        self::assertCount(2, iterator_to_array($result));
        self::assertSame('MonotonicClock: FAIL (1 errors, 1 warnings)', $result->summary());
    }
}
