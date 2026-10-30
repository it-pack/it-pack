<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Scope;

use BadMethodCallException;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope
 */
final class ReferenceExecutionScopeTest extends TestCase
{
    public function testStoresDiscriminant(): void
    {
        $scope = new ReferenceExecutionScope('test-disc');

        self::assertSame('test-disc', $scope->discriminant);
    }

    public function testExecutionThrowsBadMethodCall(): void
    {
        $this->expectException(BadMethodCallException::class);

        new ReferenceExecutionScope('x')->execution();
    }

    public function testContextThrowsBadMethodCall(): void
    {
        $this->expectException(BadMethodCallException::class);

        new ReferenceExecutionScope('x')->context();
    }

    public function testActivitiesThrowsBadMethodCall(): void
    {
        $this->expectException(BadMethodCallException::class);

        new ReferenceExecutionScope('x')->activities();
    }

    public function testMeasurementsThrowsBadMethodCall(): void
    {
        $this->expectException(BadMethodCallException::class);

        new ReferenceExecutionScope('x')->measurements();
    }

    public function testObservationsThrowsBadMethodCall(): void
    {
        $this->expectException(BadMethodCallException::class);

        new ReferenceExecutionScope('x')->observations();
    }

    public function testOccurrencesThrowsBadMethodCall(): void
    {
        $this->expectException(BadMethodCallException::class);

        new ReferenceExecutionScope('x')->occurrences();
    }

    public function testSnapshotsThrowsBadMethodCall(): void
    {
        $this->expectException(BadMethodCallException::class);

        new ReferenceExecutionScope('x')->snapshots();
    }
}
