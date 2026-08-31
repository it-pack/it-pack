<?php

declare(strict_types=1);

namespace ItPack\DeriveItRunit\Tests;

use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;
use ItPack\DeriveIt\Contract\Telemetry\DeriveObservationSeverity;
use ItPack\DeriveIt\Contract\Telemetry\DeriveUnits;
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveItRunit\DeriveItExecutions;
use ItPack\DeriveItRunit\Tests\Fixture\ReportWidget;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Domain\Measurement\MeasurementKind;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end proof of the decoupled contract: a real DeriveIt derivation runs
 * inside a real RunIt execution, and every emitted fact lands on that
 * execution's recorders — without either package importing the other.
 */
final class DerivationExecutionFunctionalTest extends TestCase
{
    private InMemoryArtifactPublisher $publisher;

    protected function setUp(): void
    {
        $this->publisher = new InMemoryArtifactPublisher();
    }

    private function runtime(): \ItPack\RunIt\SDK\RunItInterface
    {
        return RunItBuilder::create()
            ->withPublisher($this->publisher)
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->build();
    }

    /**
     * @return list<Occurrence>
     */
    private function occurrences(): array
    {
        return array_values(array_filter(
            $this->publisher->published(),
            static fn ($artifact): bool => $artifact instanceof Occurrence,
        ));
    }

    public function testDerivationInsideExecutionRecordsFullFactSet(): void
    {
        $source = DeriveItExecutions::run(
            $this->runtime(),
            'derive.flat',
            fn (DeriveEventSinkInterface $sink): string => DeriveIt::deriveWithResult(
                ReportWidget::class,
                new DeriveConfig(),
                $sink,
            )->source ?? '',
        );

        // The derivation itself is unaffected: same output shape as always.
        self::assertStringContainsString('interface ReportWidgetInterface', $source);
        self::assertStringContainsString('render(string $template): string', $source);

        $types = array_map(
            static fn (Occurrence $o): string => $o->type()->toString(),
            $this->occurrences(),
        );

        self::assertContains('deriveit.derivation.started.v1', $types);
        self::assertContains('deriveit.derivation.completed.v1', $types);
        self::assertContains('deriveit.activity.started.v1', $types);
        self::assertContains('deriveit.activity.finished.v1', $types);

        // Measurements arrive namespaced with their values and units intact.
        $measurements = array_filter(
            $this->publisher->published(),
            static fn ($artifact): bool => $artifact instanceof MeasurementInterface,
        );
        $names = array_map(
            static fn (MeasurementInterface $m): string => $m->name()->toString(),
            array_values($measurements),
        );
        self::assertContains('deriveit.methods.derived', $names);
        self::assertContains('deriveit.source.bytes', $names);
        self::assertNotContains('methods.derived', $names);

        // A clean configuration emits no observations.
        $observations = array_filter(
            $this->publisher->published(),
            static fn ($artifact): bool => $artifact instanceof Observation,
        );
        self::assertSame([], array_values($observations));
    }

    public function testMisconfiguredReExposeSurfacesAsWarningObservation(): void
    {
        $config = new DeriveConfig()->withReExposeOwnedInterfaces(['Missing\NeverImplemented']);

        DeriveItExecutions::run(
            $this->runtime(),
            'derive.flat',
            fn (DeriveEventSinkInterface $sink): string => DeriveIt::deriveWithResult(
                ReportWidget::class,
                $config,
                $sink,
            )->source ?? '',
        );

        $warnings = array_values(array_filter(
            $this->publisher->published(),
            static fn ($artifact): bool => $artifact instanceof Observation,
        ));

        self::assertCount(1, $warnings);
        self::assertSame('warning', $warnings[0]->severity()->label());
        self::assertStringContainsString('reExposeOwnedInterfaces entry', (string) $warnings[0]->message());
        self::assertStringContainsString('skipped', (string) $warnings[0]->message());
        self::assertSame('Missing\\NeverImplemented', $warnings[0]->attributes()['interface'] ?? null);
    }

    public function testDriftDetectionEmitsDetectedOccurrenceOnTheExecution(): void
    {
        DeriveItExecutions::run(
            $this->runtime(),
            'derive.diff',
            fn (DeriveEventSinkInterface $sink): \ItPack\DeriveIt\Generator\DeriveDiff =>
                DeriveIt::diff(ReportWidget::class, "stale\n", new DeriveConfig(), $sink),
        );

        $types = array_map(
            static fn (Occurrence $o): string => $o->type()->toString(),
            $this->occurrences(),
        );

        self::assertContains('deriveit.drift.detected.v1', $types);
        self::assertNotContains('deriveit.drift.clean.v1', $types);
    }

    /**
     * Exercises every sink branch directly through a captured sink so the mutation
     * tester has assertions pinning each recorder call (per-unit measurement arms,
     * per-severity observation arms, attribute/subject handling).
     */
    public function testSinkExercisesEveryRecorderBranch(): void
    {
        $sink = null;
        DeriveItExecutions::run(
            $this->runtime(),
            'sink.exercise',
            static function (DeriveEventSinkInterface $captured) use (&$sink): void {
                $sink = $captured;
            },
        );
        \assert($sink instanceof DeriveEventSinkInterface);

        $sink->activityStarted('act.start', ['k' => 'v']);
        $sink->activityFinished('act.finish');
        $sink->activityFinished('act.fail', false, ['x' => 'y']);
        $sink->measured('m.count', 3, DeriveUnits::UNIT_COUNT, ['a' => '1']);
        $sink->measured('m.bytes', 10, DeriveUnits::UNIT_BYTES);
        $sink->measured('m.ms', 1.5, DeriveUnits::UNIT_MILLISECONDS);
        $sink->measured('m.pct', 0.5, DeriveUnits::UNIT_PERCENT);
        $sink->measured('m.gauge', 2.0, 'unknown-unit');
        $sink->observed('obs.debug', DeriveObservationSeverity::DEBUG);
        $sink->observed('obs.info', DeriveObservationSeverity::INFO);
        $sink->observed('obs.warn', DeriveObservationSeverity::WARNING);
        $sink->observed('obs.error', DeriveObservationSeverity::ERROR);
        $sink->occurred('custom.type', ['target' => 'SubjectX', 'extra' => 'z']);
        $sink->occurred('custom.empty', []);

        $published = $this->publisher->published();

        $measurement = static function (string $name) use ($published): MeasurementInterface {
            foreach ($published as $artifact) {
                if (
                    $artifact instanceof MeasurementInterface
                    && $artifact->name()->toString() === 'deriveit.' . $name
                ) {
                    return $artifact;
                }
            }
            self::fail('missing measurement ' . $name);
        };

        $occurrenceByDataName = static function (string $name) use ($published): Occurrence {
            foreach ($published as $artifact) {
                if (!$artifact instanceof Occurrence) {
                    continue;
                }
                $data = $artifact->data();
                if (is_array($data) && ($data['name'] ?? null) === $name) {
                    return $artifact;
                }
            }
            self::fail('missing occurrence ' . $name);
        };

        $observationByMessage = static function (string $message) use ($published): Observation {
            foreach ($published as $artifact) {
                if ($artifact instanceof Observation && $artifact->message() === $message) {
                    return $artifact;
                }
            }
            self::fail('missing observation ' . $message);
        };

        self::assertSame('v', $occurrenceByDataName('act.start')->data()['k']);
        self::assertSame('act.start', $occurrenceByDataName('act.start')->data()['name']);

        self::assertTrue($occurrenceByDataName('act.finish')->data()['successful']);
        self::assertFalse($occurrenceByDataName('act.fail')->data()['successful']);

        self::assertSame(MeasurementKind::Counter, $measurement('m.count')->kind());
        self::assertSame(MeasurementUnit::Count, $measurement('m.count')->unit());
        self::assertSame(3, $measurement('m.count')->value());
        self::assertSame(MeasurementKind::Size, $measurement('m.bytes')->kind());
        self::assertSame(MeasurementUnit::Bytes, $measurement('m.bytes')->unit());
        self::assertSame(10, $measurement('m.bytes')->value());
        self::assertSame(MeasurementKind::Duration, $measurement('m.ms')->kind());
        self::assertSame(MeasurementUnit::Milliseconds, $measurement('m.ms')->unit());
        self::assertSame(1.5, $measurement('m.ms')->value());
        self::assertSame(MeasurementKind::Ratio, $measurement('m.pct')->kind());
        self::assertSame(MeasurementUnit::Percent, $measurement('m.pct')->unit());
        self::assertSame(0.5, $measurement('m.pct')->value());
        self::assertSame(MeasurementKind::Gauge, $measurement('m.gauge')->kind());

        self::assertSame('debug', $observationByMessage('obs.debug')->severity()->label());
        self::assertSame('info', $observationByMessage('obs.info')->severity()->label());
        self::assertSame('warning', $observationByMessage('obs.warn')->severity()->label());
        self::assertSame('error', $observationByMessage('obs.error')->severity()->label());

        $typed = null;
        $emptied = null;
        foreach ($published as $artifact) {
            if (!$artifact instanceof Occurrence) {
                continue;
            }
            $type = $artifact->type()->toString();
            if (str_ends_with($type, 'custom.type.v1')) {
                $typed = $artifact;
            }
            if (str_ends_with($type, 'custom.empty.v1')) {
                $emptied = $artifact;
            }
        }
        \assert($typed instanceof Occurrence && $emptied instanceof Occurrence);

        self::assertSame('SubjectX', $typed->subject());
        self::assertSame('z', $typed->data()['extra']);
        self::assertNull($emptied->subject());
        self::assertNull($emptied->data());
    }
}
