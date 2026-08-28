<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Codeception\Test\Unit;
use ItPack\DeriveIt\Contract\Telemetry\DeriveObservationSeverity;
use ItPack\DeriveIt\Generator\DeriveEventEmitter;
use Tests\Support\Fixture\RecordingDeriveEventSink;

/**
 * Unit contract of the telemetry pass-through: every emission must forward
 * name/severity/unit/value/attributes verbatim to a wired sink, and must be a
 * silent no-op when no sink is wired.
 */
final class DeriveEventEmitterTest extends Unit
{
    public function testForwardsEveryFactToTheWiredSink(): void
    {
        $sink = new RecordingDeriveEventSink();
        $emitter = new DeriveEventEmitter($sink);
        $attributes = ['target' => '_Fixture\Collaborator'];

        $emitter->activityStarted('graph.walk', $attributes);
        $emitter->activityFinished('graph.walk');
        $emitter->measured('methods.derived', 3, 'count', $attributes);
        $emitter->observed('skipped', DeriveObservationSeverity::WARNING, ['interface' => 'X']);
        $emitter->occurred('derivation.completed');

        self::assertSame([
            ['activity.started', 'graph.walk', $attributes],
            ['activity.finished', 'graph.walk', ['successful' => true]],
            ['measured', 'methods.derived', ['value' => 3, 'unit' => 'count'] + $attributes],
            ['observed', 'warning', ['message' => 'skipped', 'interface' => 'X']],
            ['occurred', 'derivation.completed', []],
        ], $sink->events);
    }

    public function testFinishActivityCanReportFailure(): void
    {
        $sink = new RecordingDeriveEventSink();

        new DeriveEventEmitter($sink)->activityFinished('surface.extract', false);

        self::assertSame([['activity.finished', 'surface.extract', ['successful' => false]]], $sink->events);
    }

    public function testIsASilentNoOpWithoutAWiredSink(): void
    {
        $emitter = new DeriveEventEmitter(null);
        $attributes = ['target' => 'X'];

        // None of these may throw despite the absent sink (null-safe pass-through).
        $emitter->activityStarted('a', $attributes);
        $emitter->activityFinished('a', true);
        $emitter->measured('m', 1, 'count');
        $emitter->observed('msg', DeriveObservationSeverity::INFO);
        $emitter->occurred('t');

        $this->expectNotToPerformAssertions();
    }
}
