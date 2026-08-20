<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Reporting;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Adapter\Reporting\GuardedExecutionReporter;
use ItPack\CommandIt\Contract\Port\ExecutionReporterInterface;

/**
 * Holds the reporter under test so a self-observing delegate can call back into
 * the exact guarded instance that wraps it (circular wiring for the loop test).
 */
final class SelfObserveHolder
{
    public ?GuardedExecutionReporter $reporter = null;
}


final class GuardedExecutionReporterTest extends Unit
{
    private CollectingExecutionReporter $health;

    private CollectingExecutionReporter $delegate;

    private GuardedExecutionReporter $reporter;

    protected function setUp(): void
    {
        $this->health = new CollectingExecutionReporter();
        $this->delegate = new CollectingExecutionReporter();
        $this->reporter = new GuardedExecutionReporter($this->delegate, $this->health);
    }

    public function testNormalPublicationReachesDelegate(): void
    {
        $this->reporter->reportOccurrence('command.handled.v1', ['command' => 'demo']);

        self::assertCount(1, $this->delegate->entries());
        self::assertSame('command.handled.v1', $this->delegate->entries()[0]['name']);
    }

    public function testDelegateFailureIsRoutedToHealthAndNotRethrown(): void
    {
        $failing = new class () implements ExecutionReporterInterface {
            public function reportObservation(string $message, array $context = []): void
            {
            }

            public function reportOccurrence(string $type, array $context = []): void
            {
                throw new \RuntimeException('sink down');
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

        $reporter = new GuardedExecutionReporter($failing, $this->health);

        $reporter->reportOccurrence('command.failed.v1', []);

        self::assertCount(0, $this->delegate->entries());
        $this->assertHealthContains('publication.failed');
    }

    public function testSelfObservationLoopIsBrokenByRecursionGuard(): void
    {
        $holder = new SelfObserveHolder();

        $selfObserving = new class ($holder) implements ExecutionReporterInterface {
            public int $calls = 0;

            public function __construct(
                private SelfObserveHolder $holder,
            ) {
            }

            public function reportObservation(string $message, array $context = []): void
            {
                $this->holder->reporter?->reportObservation('about.publishing', []);
            }

            public function reportOccurrence(string $type, array $context = []): void
            {
                ++$this->calls;
                $this->holder->reporter?->reportOccurrence('about.publishing', []);
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

        $reporter = new GuardedExecutionReporter($selfObserving, $this->health);
        $holder->reporter = $reporter;

        // The guard's nested call must not recurse into the delegate again.
        $reporter->reportOccurrence('command.handled.v1', ['command' => 'demo']);

        self::assertSame(1, $selfObserving->calls, 'outer publication reached the delegate exactly once');
        $this->assertHealthContains('publication.recursion-guarded');
    }

    private function assertHealthContains(string $message): void
    {
        foreach ($this->health->entries() as $entry) {
            if ($entry['type'] === 'observation' && $entry['message'] === $message) {
                return;
            }
        }

        self::fail(\sprintf('Health reporter did not record "%s".', $message));
    }
}
