<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Execution;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Execution\Timeout;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Timeout::class)]
final class TimeoutTest extends Unit
{
    public function testCreateTimeout(): void
    {
        $limit = Duration::ofSeconds(10);
        $elapsed = Duration::ofSeconds(5);
        $labels = AttributeCollection::fromArray(['env' => 'test']);

        $timeout = Timeout::of($limit, $elapsed, 'execution timed out', $labels);

        self::assertSame($limit, $timeout->limit());
        self::assertSame($elapsed, $timeout->elapsed());
        self::assertSame('execution timed out', $timeout->cause());
        self::assertSame($labels, $timeout->labels());
        self::assertNull($timeout->throwableDescription());
    }

    public function testCreateTimeoutWithDefaultCause(): void
    {
        $limit = Duration::ofSeconds(10);
        $elapsed = Duration::ofSeconds(5);

        $timeout = Timeout::of($limit, $elapsed);

        self::assertSame('execution timed out', $timeout->cause());
    }

    public function testCreateTimeoutWithNoLabels(): void
    {
        $limit = Duration::ofSeconds(10);
        $elapsed = Duration::ofSeconds(5);

        $timeout = Timeout::of($limit, $elapsed, 'custom cause');

        self::assertSame('custom cause', $timeout->cause());
        self::assertEquals(AttributeCollection::empty(), $timeout->labels());
    }
}
