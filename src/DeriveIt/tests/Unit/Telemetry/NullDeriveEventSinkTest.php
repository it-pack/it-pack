<?php

declare(strict_types=1);

namespace Tests\Unit\Telemetry;

use Codeception\Test\Unit;
use ItPack\DeriveIt\Telemetry\NullDeriveEventSink;

/**
 * No-op proof for the default telemetry sink: every method must be callable,
 * return nothing, and have zero observable side effects, so the derivation
 * pipeline's output is byte-identical whether or not a sink is wired.
 */
final class NullDeriveEventSinkTest extends Unit
{
    public function testAllMethodsAreCallableNoOps(): void
    {
        $sink = new NullDeriveEventSink();
        $attributes = ['target' => '_Fixture\Collaborator'];

        // Every emission must be a callable no-op: returns nothing, throws nothing.
        $sink->activityStarted('surface.extract', $attributes);
        $sink->activityFinished('surface.extract');
        $sink->measured('methods.derived', 3, 'count', $attributes);
        $sink->observed('notice', 'warning', $attributes);
        $sink->occurred('derivation.completed', $attributes);

        $this->expectNotToPerformAssertions();
    }

    public function testAttributesAreAcceptedAndIgnored(): void
    {
        $sink = new NullDeriveEventSink();

        // Scalar-only attribute contract: mixed scalar shapes must be accepted.
        $sink->measured('source.bytes', 1234, 'By', ['target' => 'X', 'ratio' => 0.5, 'ok' => true]);
        $sink->occurred('drift.detected');

        $this->expectNotToPerformAssertions();
    }
}
