<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Http;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Http\Propagation\DefaultHeaderPropagator;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DefaultHeaderPropagator::class)]
final class DefaultHeaderPropagatorTest extends Unit
{
    public function testHeadersIncludeExecutionAndCorrelation(): void
    {
        $propagator = new DefaultHeaderPropagator();

        $headers = $propagator->headers('exec-1', 'corr-1');

        self::assertSame('exec-1', $headers['X-Execution-Id']);
        self::assertSame('corr-1', $headers['X-Correlation-Id']);
    }

    public function testHeadersOmitCorrelationWhenNull(): void
    {
        $propagator = new DefaultHeaderPropagator();

        $headers = $propagator->headers('exec-1');

        self::assertArrayNotHasKey('X-Correlation-Id', $headers);
    }
}
