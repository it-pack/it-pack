<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Trace;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\InMemoryExecutionSpanStore;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanKindMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanStatusMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Trace\DefaultExecutionSpanMapper;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Shared\Instant;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;
use Tests\Support\Fixture\SensitiveExceptionChain;

/**
 * M9.2 — Execution to trace mapping.
 */
final class ExecutionSpanMapperTest extends Unit
{
    private InMemoryExporter $spanExporter;

    private DefaultExecutionSpanMapper $mapper;

    private InMemoryExecutionSpanStore $store;

    private TracerProviderInterface $tracerProvider;

    protected function _before(): void
    {
        $this->spanExporter = new InMemoryExporter();
        $this->tracerProvider = new TracerProvider(new SimpleSpanProcessor($this->spanExporter));
        $this->store = new InMemoryExecutionSpanStore();
        $this->mapper = new DefaultExecutionSpanMapper(
            $this->tracerProvider,
            $this->store,
            new SpanStatusMap(),
            new SpanKindMap()
        );
    }

    /**
     * @return SpanDataInterface[]
     */
    private function spans(): array
    {
        /** @var SpanDataInterface[] $spans */
        $spans = $this->spanExporter->getSpans();

        return $spans;
    }

    private function span(int $index): SpanDataInterface
    {
        return $this->spans()[$index];
    }

    public function testMapsRootExecutionToRootSpanWithAttributesAndStatus(): void
    {
        $execution = RunItArtifactFactory::execution('exec-1', 'import', 'queue.consumer.process', 'succeeded');

        $this->mapper->map($execution);

        $spans = $this->spans();
        self::assertCount(1, $spans);
        $span = $spans[0];
        self::assertSame('import', $span->getName());
        self::assertSame(StatusCode::STATUS_OK, $span->getStatus()->getCode());
        self::assertSame('exec-1', $span->getAttributes()->get('runit.execution.id'));
        self::assertSame('import', $span->getAttributes()->get('runit.execution.name'));
        self::assertSame('queue.consumer.process', $span->getAttributes()->get('runit.execution.kind'));
        self::assertSame('succeeded', $span->getAttributes()->get('runit.execution.status'));
        self::assertSame(1_000_000_000_000, $span->getStartEpochNanos());
        self::assertSame(2_000_000_000_000, $span->getEndEpochNanos());
    }

    public function testMapsSpanKindFromExecutionKind(): void
    {
        $execution = RunItArtifactFactory::execution('exec-2', 'http-in', 'http.server.request', 'succeeded');

        $this->mapper->map($execution);

        self::assertSame(2, $this->span(0)->getKind());
    }

    public function testRecordsExceptionEventForFailedExecution(): void
    {
        $execution = RunItArtifactFactory::execution('exec-3', 'import', 'queue.consumer.process', 'failed');

        $this->mapper->map($execution);

        $span = $this->span(0);
        self::assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        self::assertSame('failed', $span->getStatus()->getDescription());
        $events = $span->getEvents();
        self::assertCount(1, $events);
        self::assertSame('exception', $events[0]->getName());
        self::assertSame('RuntimeException', $events[0]->getAttributes()->get('exception.type'));
        self::assertSame('execution failed', $events[0]->getAttributes()->get('exception.message'));
    }

    public function testParentExecutionBecomesParentSpan(): void
    {
        $parent = RunItArtifactFactory::execution('exec-parent', 'parent', 'internal', 'succeeded');
        $child = RunItArtifactFactory::execution('exec-child', 'child', 'internal', 'succeeded', 'exec-parent');

        $this->mapper->map($parent);
        $this->mapper->map($child);

        $spans = $this->spans();
        self::assertCount(2, $spans);
        $childSpan = $spans[1];
        self::assertSame($spans[0]->getContext()->getSpanId(), $childSpan->getParentSpanId());
        self::assertSame('exec-parent', $childSpan->getAttributes()->get('runit.execution.parent_id'));
    }

    public function testStoresSpanContextForParenting(): void
    {
        $execution = RunItArtifactFactory::execution('exec-store', 'import', 'internal', 'succeeded');

        $this->mapper->map($execution);

        self::assertNotNull($this->store->executionContext('exec-store'));
    }

    public function testMissingTracerProviderExportsNothing(): void
    {
        $mapper = new DefaultExecutionSpanMapper(
            tracerProvider: null,
            store: $this->store,
            statusMap: new SpanStatusMap(),
            kindMap: new SpanKindMap(),
        );
        $execution = RunItArtifactFactory::execution('exec-none', 'import', 'internal', 'succeeded');

        $mapper->map($execution);

        self::assertCount(0, $this->spans());
    }

    /** Negative export assertion: execution result throwable secrets are masked before OpenTelemetry export. */
    public function testExecutionResultThrowableSecretsMaskedInSpanEvents(): void
    {
        $execution = Execution::start(
            ExecutionId::fromString('exec-secret'),
            ExecutionDefinition::create(ExecutionName::fromString('import'), ExecutionKind::fromString('batch')),
            Instant::of(1000),
        )->complete(
            ExecutionFailure::of(ExecutionStatus::Failed, SensitiveExceptionChain::classifiedChain()),
            Instant::of(1001),
        );

        // Default redaction policy is active: throwables under result.throwable are masked.
        $redacted = new DefaultArtifactRedactor()->redact($execution);
        self::assertInstanceOf(RedactedArtifactInterface::class, $redacted);
        $normalized = $redacted->normalize();

        $mapper = new DefaultExecutionSpanMapper(
            tracerProvider: $this->tracerProvider,
            store: $this->store,
            statusMap: new SpanStatusMap(),
            kindMap: new SpanKindMap(),
        );
        $mapper->mapRedactedExecution($normalized);

        $span = $this->span(0);
        $events = $span->getEvents();
        self::assertCount(1, $events);
        self::assertSame('exception', $events[0]->getName());
        $attrs = $events[0]->getAttributes();
        $message = $attrs->get('exception.message');
        self::assertIsString($message);
        // Nested causes are not flattened into separate span events; masked outer message contains no classified secret.
        foreach (SensitiveExceptionChain::secrets() as $secret) {
            self::assertStringNotContainsString($secret, $message);
        }

        // Opt-out: disable throwable redaction and confirm secrets reach span events.
        $mapperOptOut = new DefaultExecutionSpanMapper(
            tracerProvider: $this->tracerProvider,
            store: $this->store,
            statusMap: new SpanStatusMap(),
            kindMap: new SpanKindMap(),
        );
        $executionOptOut = Execution::start(
            ExecutionId::fromString('exec-optout'),
            ExecutionDefinition::create(ExecutionName::fromString('import'), ExecutionKind::fromString('batch')),
            Instant::of(1000),
        )->complete(
            ExecutionFailure::of(ExecutionStatus::Failed, SensitiveExceptionChain::classifiedChain()),
            Instant::of(1001),
        );
        // No redactor applied upstream: secrets reach span events.
        $mapperOptOut->map($executionOptOut);
        $spanOptOut = $this->span(1);
        $eventsOptOut = $spanOptOut->getEvents();
        self::assertCount(1, $eventsOptOut);
        $attrsOptOut = $eventsOptOut[0]->getAttributes();
        $messageOptOut = $attrsOptOut->get('exception.message');
        self::assertIsString($messageOptOut);
        self::assertStringContainsString(SensitiveExceptionChain::secrets()[1], $messageOptOut);
    }
}
