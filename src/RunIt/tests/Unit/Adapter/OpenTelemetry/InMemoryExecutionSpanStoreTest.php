<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Adapter\OpenTelemetry\InMemoryExecutionSpanStore;
use OpenTelemetry\API\Trace\SpanContextInterface;

final class InMemoryExecutionSpanStoreTest extends Unit
{
    public function testAggregateFifoAndReadsDoNotRefreshEntries(): void
    {
        $store = new InMemoryExecutionSpanStore(2);
        $execution = self::createStub(SpanContextInterface::class);
        $activity = self::createStub(SpanContextInterface::class);
        $new = self::createStub(SpanContextInterface::class);
        $store->recordExecution('same', $execution);
        $store->recordActivity('same', $activity);
        self::assertSame($execution, $store->executionContext('same'));
        $store->recordExecution('new', $new);

        self::assertNull($store->executionContext('same'));
        self::assertSame($activity, $store->activityContext('same'));
        self::assertSame($new, $store->executionContext('new'));
        $store->recordActivity('new', $new);
        self::assertNull($store->activityContext('same'));
    }

    #[DataProvider('kinds')]
    public function testReplacementRefreshesOrder(bool $execution): void
    {
        $store = new InMemoryExecutionSpanStore(2);
        $old = self::createStub(SpanContextInterface::class);
        $replacement = self::createStub(SpanContextInterface::class);
        $record = $execution ? $store->recordExecution(...) : $store->recordActivity(...);
        $lookup = $execution ? $store->executionContext(...) : $store->activityContext(...);
        $record('first', $old);
        $record('second', $old);
        $record('first', $replacement);
        $record('third', $old);

        self::assertSame($replacement, $lookup('first'));
        self::assertNull($lookup('second'));
        self::assertSame($old, $lookup('third'));
    }

    /** @return array<string, array{bool}> */
    public static function kinds(): array
    {
        return [
            'execution' => [true],
            'activity' => [false],
        ];
    }

    #[DataProvider('invalidCapacities')]
    public function testRejectsInvalidCapacity(int $capacity): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InMemoryExecutionSpanStore($capacity);
    }

    /** @return array<string, array{int}> */
    public static function invalidCapacities(): array
    {
        return ['zero' => [0], 'negative' => [-1]];
    }

    public function testSustainedChurnAndDefaultCapacity(): void
    {
        $store = new InMemoryExecutionSpanStore();
        $context = self::createStub(SpanContextInterface::class);
        for ($i = 0; $i < 20000; ++$i) {
            if ($i % 2 === 0) {
                $store->recordExecution((string) $i, $context);
            } else {
                $store->recordActivity((string) $i, $context);
            }
        }

        self::assertNull($store->executionContext('9998'));
        self::assertNull($store->activityContext('9999'));
        self::assertSame($context, $store->executionContext('10000'));
        self::assertSame($context, $store->activityContext('19999'));
    }
}
