<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use _Fixture\Collaborator;
use _Fixture\Stubs\ClassA;
use _Fixture\Stubs\ClassB;
use _Fixture\UsesMultipleTraitsClass;
use Codeception\Test\Unit;
use ItPack\DeriveIt\Contract\Telemetry\DeriveEvents;
use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;
use ItPack\DeriveIt\Contract\Telemetry\DeriveMeasurements;
use ItPack\DeriveIt\Contract\Telemetry\DeriveObservationSeverity;
use ItPack\DeriveIt\DeriveItFactory;
use ItPack\DeriveIt\Generator\DeriveConfig;

/**
 * Records every emitted telemetry fact as `[kind, name, attributes]` tuples so
 * tests can assert exact event sequences (CONSOLIDATION_PLAN.md §5.2 / C2).
 */
final class RecordingDeriveEventSink implements DeriveEventSinkInterface
{
    /** @var list<array{string, string, array<string, string|int|float|bool|null>}> */
    public array $events = [];

    public function activityStarted(string $name, array $attributes = []): void
    {
        $this->events[] = ['activity.started', $name, $attributes];
    }

    public function activityFinished(string $name, bool $successful = true, array $attributes = []): void
    {
        $this->events[] = ['activity.finished', $name, ['successful' => $successful] + $attributes];
    }

    public function measured(string $name, int|float $value, string $unit, array $attributes = []): void
    {
        $this->events[] = ['measured', $name, ['value' => $value, 'unit' => $unit] + $attributes];
    }

    public function observed(string $message, DeriveObservationSeverity $severity, array $attributes = []): void
    {
        $this->events[] = ['observed', $severity->value, ['message' => $message] + $attributes];
    }

    public function occurred(string $type, array $attributes = []): void
    {
        $this->events[] = ['occurred', $type, $attributes];
    }

    /**
     * @return list<string> `kind:name` pairs, the event skeleton in order.
     */
    public function kinds(): array
    {
        return array_map(static fn (array $event): string => $event[0] . ':' . $event[1], $this->events);
    }
}

/**
 * Emission-point contract for C2: lifecycle occurrences bracket every
 * derivation, activities wrap the pipeline stages, measurements carry the
 * countable facts, and a misconfigured re-expose list surfaces as an
 * observation instead of being skipped silently.
 */
final class DerivationTelemetryTest extends Unit
{
    private RecordingDeriveEventSink $sink;

    private DeriveItFactory $factory;

    protected function _before(): void
    {
        $this->sink = new RecordingDeriveEventSink();
        $this->factory = new DeriveItFactory(new DeriveConfig(), null, null, $this->sink);
    }

    public function testFlatDerivationEmitsLifecycleInOrder(): void
    {
        // Expected surface size comes from a separate, silent factory so this
        // recording sink only captures the deriveSource() run under test.
        $expectedMethods = count(new DeriveItFactory()->interfaceDeriver()->analyze(Collaborator::class)->methods);

        $source = $this->factory->interfaceDeriver()->deriveSource(Collaborator::class);

        self::assertSame([
            'occurred:' . DeriveEvents::EVENT_DERIVATION_STARTED,
            'activity.started:' . DeriveEvents::ACTIVITY_SURFACE_EXTRACT,
            'activity.finished:' . DeriveEvents::ACTIVITY_SURFACE_EXTRACT,
            'measured:methods.derived',
            'measured:methods.dropped',
            'activity.started:' . DeriveEvents::ACTIVITY_LEAKAGE_AUDIT,
            'activity.finished:' . DeriveEvents::ACTIVITY_LEAKAGE_AUDIT,
            'measured:' . DeriveMeasurements::MEASUREMENT_SUBSTITUTIONS_APPLIED,
            'occurred:' . DeriveEvents::EVENT_DERIVATION_COMPLETED,
            'measured:' . DeriveMeasurements::MEASUREMENT_SOURCE_BYTES,
        ], $this->sink->kinds());

        // The derived-method measurement matches the actual surface; rendering
        // measured bytes equal the returned source length.
        $derived = $this->sink->events[3][2];
        self::assertSame($expectedMethods, $derived['value']);
        self::assertSame('count', $derived['unit']);
        self::assertSame(Collaborator::class, $derived['target']);

        $bytes = $this->sink->events[9][2];
        self::assertSame(strlen($source), $bytes['value']);
        self::assertSame('By', $bytes['unit']);
    }

    public function testFamilyDerivationMeasuresMembersThenEachMemberLifecycle(): void
    {
        $this->factory->familyDeriver()->derive([ClassA::class, ClassB::class]);

        $kinds = $this->sink->kinds();

        self::assertSame('measured:' . DeriveMeasurements::MEASUREMENT_FAMILY_MEMBERS, $kinds[0]);
        self::assertSame(['value' => 2, 'unit' => 'count', 'graph' => 'classes'], $this->sink->events[0][2]);

        // Two complete member lifecycles follow, one per input class.
        $starts = array_keys($kinds, 'occurred:' . DeriveEvents::EVENT_DERIVATION_STARTED, true);
        $completes = array_keys($kinds, 'occurred:' . DeriveEvents::EVENT_DERIVATION_COMPLETED, true);
        self::assertCount(2, $starts);
        self::assertCount(2, $completes);
        self::assertSame(ClassA::class, $this->sink->events[$starts[0]][2]['target']);
        self::assertSame(ClassB::class, $this->sink->events[$starts[1]][2]['target']);
    }

    public function testPerTraitDerivationEmitsTraitAndMainSurfaces(): void
    {
        $this->factory->perTraitDeriver()->deriveSource(UsesMultipleTraitsClass::class);

        $kinds = $this->sink->kinds();

        // Lifecycle brackets the whole run; trait graph walk and both surface
        // extractions are distinguishable by their attributes.
        self::assertSame('occurred:' . DeriveEvents::EVENT_DERIVATION_STARTED, $kinds[0]);
        self::assertContains('activity.started:' . DeriveEvents::ACTIVITY_GRAPH_WALK, $kinds);
        self::assertContains('measured:' . DeriveMeasurements::MEASUREMENT_FAMILY_MEMBERS, $kinds);

        $surfaces = array_values(array_filter(
            $this->sink->events,
            static fn (array $e): bool => $e[0] === 'activity.started' && $e[1] === 'surface.extract',
        ));
        self::assertCount(2, $surfaces);
        self::assertSame('trait-interfaces', $surfaces[0][2]['surface']);
        self::assertSame('main', $surfaces[1][2]['surface']);

        self::assertContains('occurred:' . DeriveEvents::EVENT_DERIVATION_COMPLETED, $kinds);
    }

    public function testUnimplementedReExposeEntryIsObservedAsWarning(): void
    {
        $config = new DeriveConfig()->withReExposeOwnedInterfaces(['_Fixture\NeverImplementedInterface']);
        $wired = new DeriveItFactory($config, null, null, $this->sink)->interfaceDeriver();
        $wired->deriveSource(Collaborator::class);

        $warnings = array_values(array_filter(
            $this->sink->events,
            static fn (array $e): bool => $e[0] === 'observed' && $e[1] === 'warning',
        ));

        self::assertCount(1, $warnings);
        self::assertSame('_Fixture\NeverImplementedInterface', $warnings[0][2]['interface']);
        self::assertStringContainsString('skipped', (string) $warnings[0][2]['message']);
    }

    public function testWiredAndUnwiredFactoriesProduceIdenticalSources(): void
    {
        $silent = new DeriveItFactory()->interfaceDeriver()->deriveSource(Collaborator::class);
        $wired = $this->factory->interfaceDeriver()->deriveSource(Collaborator::class);

        self::assertSame($silent, $wired);
        self::assertNotSame([], $this->sink->events);
    }

    public function testDiffEmitsComputeActivityAndDriftOccurrence(): void
    {
        $source = new DeriveItFactory()->interfaceDeriver()->deriveSource(Collaborator::class);

        \ItPack\DeriveIt\DeriveIt::diff(Collaborator::class, $source, new DeriveConfig(), $this->sink);

        $kinds = $this->sink->kinds();
        // The diff's own activity wraps the nested derivation lifecycle emitted
        // by deriveSource().
        self::assertContains('activity.started:' . DeriveEvents::ACTIVITY_DIFF_COMPUTE, $kinds);
        self::assertContains('activity.finished:' . DeriveEvents::ACTIVITY_DIFF_COMPUTE, $kinds);
        self::assertContains('occurred:' . DeriveEvents::OCCURRENCE_DRIFT_CLEAN, $kinds);
        self::assertNotContains('occurred:' . DeriveEvents::OCCURRENCE_DRIFT_DETECTED, $kinds);
    }

    public function testDiffEmitsDetectedWhenSourcesDiverge(): void
    {
        \ItPack\DeriveIt\DeriveIt::diff(Collaborator::class, "stale source\n", new DeriveConfig(), $this->sink);

        self::assertContains('occurred:' . DeriveEvents::OCCURRENCE_DRIFT_DETECTED, $this->sink->kinds());
        self::assertSame(
            Collaborator::class,
            $this->sink->events[
                array_search(
                    'occurred:' . DeriveEvents::OCCURRENCE_DRIFT_DETECTED,
                    $this->sink->kinds(),
                    true,
                )
            ][2]['target'],
        );
    }
}
