<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Reporting;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Adapter\Reporting\SafeReporterFactory;
use ItPack\CommandIt\Contract\Port\ExecutionReporterInterface;

/**
 * @covers \ItPack\CommandIt\Adapter\Reporting\SafeReporterFactory
 */
final class SafeReporterFactoryTest extends Unit
{
    public function testWrapRedactsSensitiveContextBeforeDelegate(): void
    {
        $delegate = new CollectingExecutionReporter();
        $reporter = new SafeReporterFactory()->wrap($delegate);

        $reporter->reportOccurrence('command.received.v1', ['token' => 'super-secret']);

        $entries = $delegate->entries();
        self::assertCount(1, $entries);
        $context = (array) $entries[0]['context'];
        self::assertSame('***redacted***', $context['token']);
    }

    public function testWrapRoutesRecursionToHealth(): void
    {
        $health = new CollectingExecutionReporter();

        // A delegate that reports back through the very reporter it was given.
        // Without the guard this would loop forever.
        $loopingDelegate = new class () implements ExecutionReporterInterface {
            public ?ExecutionReporterInterface $reporter = null;

            public function reportObservation(string $message, array $context = []): void
            {
                $this->reporter?->reportObservation('echo: ' . $message, $context);
            }

            public function reportOccurrence(string $type, array $context = []): void
            {
            }

            public function reportMeasurement(string $name, int|float $value, string $unit): void
            {
            }

            public function beginActivity(string $name): string
            {
                return '';
            }

            public function endActivity(string $activityId): void
            {
            }
        };

        $reporter = new SafeReporterFactory(health: $health)->wrap($loopingDelegate);
        $loopingDelegate->reporter = $reporter;

        $reporter->reportObservation('hello', []);

        // The loop was broken: the nested call was routed to health, not the delegate.
        self::assertNotEmpty($health->entries());
    }
}
