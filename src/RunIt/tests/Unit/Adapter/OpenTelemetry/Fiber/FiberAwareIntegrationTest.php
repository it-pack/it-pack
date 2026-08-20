<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Fiber;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;
use OpenTelemetry\Context\Context;
use Tests\Support\Fixture\OpenTelemetry\OtelTestKit;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;

/**
 * M9.8 — Fiber-aware integration.
 *
 * The bridge keeps correlation in an explicit, id-keyed span store rather than in
 * global mutable context, so invoking the publisher from multiple fibers cannot
 * leak one execution's parent linkage into another. These tests exercise mapping
 * from within Fibers and assert the bridge degrades gracefully when no
 * Fiber-aware context storage is present.
 */
final class FiberAwareIntegrationTest extends Unit
{
    public function testMappingFromMultipleFibersDoesNotCrossContaminate(): void
    {
        $kit = new OtelTestKit();
        $config = new BridgeConfigBuilder()
            ->withTracerProvider($kit->tracerProvider)
            ->withMeterProvider($kit->meterProvider)
            ->withLoggerProvider($kit->loggerProvider)
            ->build()
        ;
        $publisher = TelemetryPublisher::fromConfig($config);

        $initialContext = Context::getCurrent();

        $fiberA = new \Fiber(function () use ($publisher, $initialContext): void {
            Context::storage()->attach($initialContext);
            $publisher->publish(RunItArtifactFactory::execution('exec-a', 'root-a', 'internal', 'succeeded'));
            $publisher->publish(RunItArtifactFactory::activity('act-a', 'exec-a', 'parse', 'internal', 'succeeded'));
        });
        $fiberB = new \Fiber(function () use ($publisher, $initialContext): void {
            Context::storage()->attach($initialContext);
            $publisher->publish(RunItArtifactFactory::execution('exec-b', 'root-b', 'internal', 'succeeded'));
            $publisher->publish(RunItArtifactFactory::activity('act-b', 'exec-b', 'parse', 'internal', 'succeeded'));
        });

        $fiberA->start();
        $fiberB->start();

        $spans = $kit->spans();
        self::assertCount(4, $spans);

        $executions = [];
        $activities = [];
        foreach ($spans as $span) {
            $attributes = $span->getAttributes();
            if ($attributes->get('runit.activity.id') !== null) {
                $activities[] = $span;
            } else {
                $executions[] = $span;
            }
        }

        self::assertCount(2, $executions);
        self::assertCount(2, $activities);

        foreach ($activities as $activity) {
            $activityExecId = $activity->getAttributes()->get('runit.execution.id');
            foreach ($executions as $execution) {
                if ($execution->getAttributes()->get('runit.execution.id') === $activityExecId) {
                    self::assertSame(
                        $execution->getContext()->getSpanId(),
                        $activity->getParentSpanId(),
                    );
                }
            }
        }
    }

    public function testBridgeWorksWithoutFiberAwareContextStorage(): void
    {
        $kit = new OtelTestKit();
        $publisher = TelemetryPublisher::fromConfig(
            new BridgeConfigBuilder()
                ->withTracerProvider($kit->tracerProvider)
                ->build(),
        );

        $publisher->publish(RunItArtifactFactory::execution('exec-1', 'root', 'internal', 'succeeded'));

        self::assertCount(1, $kit->spans());
    }
}
