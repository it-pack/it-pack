<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Execution;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Execution\Cancellation;
use ItPack\RunIt\Domain\Execution\Timeout;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Duration;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;

final class CancellationTimeoutTest extends Unit
{
    public function testCancellationPreservesCauseAndLabels(): void
    {
        $labels = AttributeCollection::empty()->with('reason', 'backpressure');
        $cancellation = Cancellation::of('throttled', $labels);

        self::assertSame('throttled', $cancellation->cause());
        self::assertSame('backpressure', $cancellation->labels()->get('reason'));
        self::assertNull($cancellation->throwableDescription());
    }

    public function testCancellationUsesProvidedDescription(): void
    {
        $description = ThrowableDescription::create('RunIt\Cancellation', 'cooperative stop', 0, previous: null, trace: null);
        $cancellation = Cancellation::of('stopped', description: $description);

        self::assertSame($description, $cancellation->throwableDescription());
    }

    public function testTimeoutCarriesLimitElapsedAndCause(): void
    {
        $timeout = Timeout::of(Duration::ofSeconds(3), Duration::ofSeconds(9), 'request timeout');

        self::assertSame(3, $timeout->limit()->seconds());
        self::assertSame(9, $timeout->elapsed()->seconds());
        self::assertSame('request timeout', $timeout->cause());
    }
}
