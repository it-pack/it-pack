<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;

use function count;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Core\ConfigurationDiffer;
use ItPack\ConfigureIt\Core\ConfigurationDriftDetector;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;
use ItPack\ConfigureIt\ValueObject\SourceKind;

class ConfigurationDriftDetectorTest extends Unit
{
    public function testBaselineMatchProducesNoDrift(): void
    {
        $reporter = new ResolutionReporter();
        $detector = new ConfigurationDriftDetector(
            liveProvider: static fn (): ConfigurationGraphInterface => self::graphA(),
            baseline: self::graphA(),
            reporter: $reporter,
        );

        verify($detector->check())->false();
        verify($reporter->observations())->equals([]);
        verify($reporter->occurrences())->equals([]);
    }

    public function testDivergenceDetectedAndReported(): void
    {
        $reporter = new ResolutionReporter();
        $captured = null;

        $detector = new ConfigurationDriftDetector(
            liveProvider: static fn (): ConfigurationGraphInterface => self::graphB(),
            baseline: self::graphA(),
            reporter: $reporter,
        );
        $detector->onDrift(static function ($diff, $live, $baseline) use (&$captured): void {
            $captured = [$diff, $live, $baseline];
        });

        verify($detector->check())->true();

        $observations = $reporter->observations();
        verify(count($observations))->equals(1);
        verify($observations[0]->level())->equals('warning');

        $occurrences = $reporter->occurrences();
        verify(count($occurrences))->equals(1);
        verify($occurrences[0]->type())->equals('ConfigurationDrift');
        verify($occurrences[0]->payload()['added'])->equals(1);
        verify($occurrences[0]->payload()['changed'])->equals(1);

        verify($captured)->notNull();
        /** @var array{0:\ItPack\ConfigureIt\ValueObject\ConfigurationDiff, 1:ConfigurationGraphInterface, 2:ConfigurationGraphInterface} $captured */
        verify($captured[2]->toArray())->equals(self::graphA()->toArray());
    }

    public function testDriftResolvedWhenLiveReturnsToBaseline(): void
    {
        $reporter = new ResolutionReporter();
        $sequence = [self::graphA(), self::graphB(), self::graphA()];
        $index = 0;

        $detector = new ConfigurationDriftDetector(
            liveProvider: static function () use (&$index, $sequence): ConfigurationGraphInterface {
                $graph = $sequence[$index] ?? self::graphA();
                ++$index;

                return $graph;
            },
            reporter: $reporter,
        );

        verify($detector->check())->equals(false); // first call pins baseline
        verify($detector->check())->true();        // divergence
        verify($detector->check())->false();       // back to baseline

        $occurrences = $reporter->occurrences();
        verify(count($occurrences))->equals(1);
        verify($occurrences[0]->type())->equals('ConfigurationDrift');

        $observations = $reporter->observations();
        verify(count($observations))->equals(2);
        verify($observations[0]->level())->equals('warning');
        verify($observations[1]->level())->equals('info');
    }

    public function testWatchStopsOnDriftAndDoesNotSpam(): void
    {
        $reporter = new ResolutionReporter();
        $sequence = [self::graphA(), self::graphB(), self::graphB()];
        $index = 0;

        $detector = new ConfigurationDriftDetector(
            liveProvider: static function () use (&$index, $sequence): ConfigurationGraphInterface {
                $graph = $sequence[$index] ?? self::graphA();
                ++$index;

                return $graph;
            },
            reporter: $reporter,
            differ: new ConfigurationDiffer(),
            intervalSeconds: 0.0,
        );

        $detector->onDrift(static function () use ($detector): void {
            $detector->stop();
        });

        $detector->watch();

        // Drift reported once (transition) even though the live graph stays
        // diverged on the third check inside the loop.
        verify(count($reporter->occurrences()))->equals(1);
    }

    /**
     * A steady divergence keeps answering "drifted" without re-emitting the
     * occurrence: the short-circuit on an unchanged live hash replays the last
     * verdict instead of re-diffing.
     */
    public function testUnchangedLiveGraphReplaysTheLastVerdictWithoutReporting(): void
    {
        $reporter = new ResolutionReporter();
        $detector = new ConfigurationDriftDetector(
            liveProvider: static fn (): ConfigurationGraphInterface => self::graphB(),
            baseline: self::graphA(),
            reporter: $reporter,
        );

        verify($detector->check())->true();  // transition -> reported
        verify($detector->check())->true();  // same hash -> replayed verdict
        verify($detector->check())->true();

        verify(count($reporter->occurrences()))->equals(1);
        verify(count($reporter->observations()))->equals(1);
    }

    public function testUnchangedMatchingGraphKeepsAnsweringNoDrift(): void
    {
        $reporter = new ResolutionReporter();
        $detector = new ConfigurationDriftDetector(
            liveProvider: static fn (): ConfigurationGraphInterface => self::graphA(),
            reporter: $reporter,
        );

        verify($detector->check())->false(); // pins the baseline
        verify($detector->check())->false(); // unchanged hash -> replayed verdict
        verify($detector->check())->false();

        verify($reporter->occurrences())->equals([]);
        verify($reporter->observations())->equals([]);
    }

    /**
     * pin() re-anchors the baseline to whatever is live *now*, so a deliberate
     * change is accepted instead of being reported as drift forever.
     */
    public function testPinRebaselinesTheLiveGraphAndClearsReportedDrift(): void
    {
        $reporter = new ResolutionReporter();
        $live = self::graphA();

        $detector = new ConfigurationDriftDetector(
            liveProvider: static function () use (&$live): ConfigurationGraphInterface {
                return $live;
            },
            baseline: self::graphA(),
            reporter: $reporter,
        );

        $live = self::graphB();
        verify($detector->check())->true();
        verify(count($reporter->occurrences()))->equals(1);

        // Accept the new shape as the reference.
        $detector->pin();

        verify($detector->check())->false();
        verify(count($reporter->occurrences()))->equals(1);
        // No "drift resolved" observation: the drift was pinned away, not undone.
        verify(count($reporter->observations()))->equals(1);
    }

    public function testPinBeforeAnyCheckMakesTheFirstCheckSilent(): void
    {
        $reporter = new ResolutionReporter();
        $detector = new ConfigurationDriftDetector(
            liveProvider: static fn (): ConfigurationGraphInterface => self::graphB(),
            reporter: $reporter,
        );

        $detector->pin();

        verify($detector->check())->false();
        verify($reporter->occurrences())->equals([]);
        verify($reporter->observations())->equals([]);
    }

    public function testDriftIsDetectedWithoutAReporter(): void
    {
        $detector = new ConfigurationDriftDetector(
            liveProvider: static fn (): ConfigurationGraphInterface => self::graphB(),
            baseline: self::graphA(),
        );

        $diffs = [];
        $detector->onDrift(static function ($diff) use (&$diffs): void {
            $diffs[] = $diff;
        });

        verify($detector->check())->true();
        verify(count($diffs))->equals(1);
        verify($diffs[0]->empty())->false();
    }

    public function testDriftResolutionIsSilentWhenNoDriftWasEverReported(): void
    {
        $reporter = new ResolutionReporter();
        $live = self::graphA();

        $detector = new ConfigurationDriftDetector(
            liveProvider: static function () use (&$live): ConfigurationGraphInterface {
                return $live;
            },
            baseline: self::graphA(),
            reporter: $reporter,
        );

        verify($detector->check())->false();

        // A different-but-equivalent graph: the hash changes, the diff does not.
        $live = new ConfigurationGraph([
            self::node('b', 2, 'base.json', 11),
            self::node('a', 1, 'base.json', 11),
        ]);

        verify($detector->check())->false();
        verify($reporter->occurrences())->equals([]);
        // Nothing was ever reported, so there is nothing to "resolve".
        verify($reporter->observations())->equals([]);
    }
    private static function node(string $key, mixed $value, string $source, int $priority): ConfigurationNode
    {
        return new ConfigurationNode(
            $key,
            $value,
            $source,
            $priority,
            [],
            new Provenance($source, Format::JSON, 'JsonLoader', $priority, SourceKind::File),
        );
    }

    private static function graphA(): ConfigurationGraphInterface
    {
        return new ConfigurationGraph([
            self::node('a', 1, 'base.json', 10),
            self::node('b', 2, 'base.json', 10),
        ]);
    }

    private static function graphB(): ConfigurationGraphInterface
    {
        return new ConfigurationGraph([
            self::node('a', 1, 'base.json', 10),
            self::node('b', 20, 'local.json', 20),
            self::node('c', 3, 'local.json', 20),
        ]);
    }
}
