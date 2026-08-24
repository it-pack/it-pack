<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use Codeception\Test\Unit;
use ItPack\DeriveIt\Cli\DeriveConsoleSink;
use ItPack\DeriveIt\Contract\Telemetry\DeriveObservationSeverity;

/**
 * Line-format contract of the `--verbose` diagnostics sink: every fact becomes
 * one `derive: …` line, observations carry a per-severity marker character, and
 * non-empty attributes render as compact JSON.
 */
final class DeriveConsoleSinkTest extends Unit
{
    /** @var list<string> */
    private array $lines = [];

    private DeriveConsoleSink $sink;

    protected function _before(): void
    {
        $this->lines = [];
        $this->sink = new DeriveConsoleSink(function (string $line): void {
            $this->lines[] = $line;
        });
    }

    public function testObservationsCarryPerSeverityMarkers(): void
    {
        $cases = [
            [DeriveObservationSeverity::DEBUG, '.'],
            [DeriveObservationSeverity::INFO, 'i'],
            [DeriveObservationSeverity::WARNING, '!'],
            [DeriveObservationSeverity::ERROR, 'x'],
        ];

        foreach ($cases as [$severity, $marker]) {
            $this->lines = [];
            $this->sink->observed('something noticed', $severity, ['target' => 'X']);

            self::assertSame(
                ['derive: [' . $marker . '] something noticed {"target":"X"}'],
                $this->lines,
            );
        }
    }

    public function testActivityMeasureAndOccurrenceFormats(): void
    {
        $this->sink->activityStarted('graph.walk', ['root' => 'A']);
        $this->sink->activityFinished('surface.extract');
        $this->sink->measured('methods.derived', 3, 'count', ['target' => 'A']);
        $this->sink->occurred('drift.detected');

        self::assertSame([
            'derive: [activity] graph.walk started {"root":"A"}',
            'derive: [activity] surface.extract done',
            'derive: [measure] methods.derived = 3 count {"target":"A"}',
            'derive: [event] drift.detected',
        ], $this->lines);
    }

    public function testFailedActivityIsMarked(): void
    {
        $this->sink->activityFinished('surface.extract', false);

        self::assertSame(['derive: [activity] surface.extract done (failed)'], $this->lines);
    }
}
