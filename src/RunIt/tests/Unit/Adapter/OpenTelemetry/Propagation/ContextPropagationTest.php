<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Propagation;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\Propagation\DefaultContextToOtelMapper;
use ItPack\RunIt\Adapter\OpenTelemetry\Propagation\DefaultOtelToContextExtractor;
use OpenTelemetry\API\Baggage\Propagation\BaggagePropagator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;

/**
 * M9.4 — Context and propagation.
 */
final class ContextPropagationTest extends Unit
{
    private TextMapPropagatorInterface $propagator;

    private DefaultContextToOtelMapper $mapper;

    private DefaultOtelToContextExtractor $extractor;

    protected function _before(): void
    {
        $this->propagator = new MultiTextMapPropagator([
            TraceContextPropagator::getInstance(),
            BaggagePropagator::getInstance(),
        ]);
        $this->mapper = new DefaultContextToOtelMapper();
        $this->extractor = new DefaultOtelToContextExtractor();
    }

    public function testSecretContextIsNeverPropagated(): void
    {
        $context = RunItArtifactFactory::contextWithSecretAndPublic();

        $baggage = $this->mapper->toBaggage($context);

        $keys = [];
        foreach ($baggage->getAll() as $key => $entry) {
            $keys[] = $key;
        }
        self::assertContains('tenant.id', $keys);
        self::assertContains('correlation.id', $keys);
        self::assertNotContains('secret.token', $keys);
    }

    public function testInjectExtractRoundTripPreservesPublicAndDropsSecret(): void
    {
        $context = RunItArtifactFactory::contextWithSecretAndPublic();

        $carrier = $this->mapper->injectIntoCarrier([], $context, $this->propagator);
        self::assertArrayHasKey('baggage', $carrier);

        $otelContext = $this->extractor->extractContext($carrier, $this->propagator);
        $recovered = $this->extractor->toRunItContext($otelContext);

        $recoveredKeys = [];
        foreach ($recovered->entries() as $entry) {
            $recoveredKeys[$entry->key()->toString()] = $entry->value();
        }

        self::assertSame('acme', $recoveredKeys['tenant.id'] ?? null);
        self::assertSame('corr-123', $recoveredKeys['correlation.id'] ?? null);
        self::assertArrayNotHasKey('secret.token', $recoveredKeys);
    }

    public function testRemoteParentTraceIsRecorded(): void
    {
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor(new InMemoryExporter()));
        $span = $tracerProvider->getTracer('test')->spanBuilder('root')->startSpan();
        $scope = $span->activate();

        try {
            $context = RunItArtifactFactory::contextWithSecretAndPublic();
            $carrier = $this->mapper->injectIntoCarrier([], $context, $this->propagator);
        } finally {
            $scope->detach();
        }

        $otelContext = $this->extractor->extractContext($carrier, $this->propagator);
        $recovered = $this->extractor->toRunItContext($otelContext);

        $found = false;
        foreach ($recovered->entries() as $entry) {
            if ($entry->key()->toString() === 'runit.trace.parent_id') {
                $found = true;
            }
        }
        self::assertTrue($found, 'remote parent trace id should be recorded');
    }

    public function testMalformedCarrierDoesNotThrow(): void
    {
        $carrier = ['traceparent' => 'not-a-valid-traceparent'];

        $otelContext = $this->extractor->extractContext($carrier, $this->propagator);
        $recovered = $this->extractor->toRunItContext($otelContext);

        self::assertInstanceOf(\ItPack\RunIt\Contract\Domain\ExecutionContextInterface::class, $recovered);
    }
}
