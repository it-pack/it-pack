<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
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
        verify(\count($observations))->equals(1);
        verify($observations[0]->level())->equals('warning');

        $occurrences = $reporter->occurrences();
        verify(\count($occurrences))->equals(1);
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
        verify(\count($occurrences))->equals(1);
        verify($occurrences[0]->type())->equals('ConfigurationDrift');

        $observations = $reporter->observations();
        verify(\count($observations))->equals(2);
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
        verify(\count($reporter->occurrences()))->equals(1);
    }
}
