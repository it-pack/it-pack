<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Observability;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Adapter\Observability\OtelHashingObserver;
use ItPack\HashIt\Contract\HashingObserverInterface;
use ItPack\HashIt\HashObservation;
use ItPack\HashIt\HashOperation;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ScopeInterface;
use RuntimeException;
use Throwable;

class OtelHashingObserverTest extends Unit
{
    private SpyTracer $tracer;

    protected function setUp(): void
    {
        $this->tracer = new SpyTracer();
    }

    public function testCompletedOperationProducesOkSpanWithAttributes(): void
    {
        $observer = new OtelHashingObserver($this->tracer);
        $id = 'abc123';

        $observer->observe(HashObservation::started(HashOperation::HASH, $id, 'sha256', 42));
        $observer->observe(HashObservation::progress($id, 21, 42));
        $observer->observe(HashObservation::progress($id, 42, 42));
        $observer->observe(HashObservation::completed($id, 'sha256', 42, 7, 'urn:hashit:sha256:deadbeef'));

        self::assertCount(1, $this->tracer->spans);

        $span = $this->tracer->spans[0];
        self::assertSame('HASH', $span->name);
        self::assertSame(StatusCode::STATUS_OK, $span->status);
        self::assertTrue($span->ended);
        self::assertSame('sha256', $span->attributes['hashit.algorithm']);
        self::assertSame(42, $span->attributes['hashit.bytes_total']);
        self::assertSame(42, $span->attributes['hashit.bytes_processed']);
        self::assertSame(7, $span->attributes['hashit.duration_ms']);
        self::assertSame('urn:hashit:sha256:deadbeef', $span->attributes['hashit.result_urn']);
    }

    public function testFailedOperationProducesErrorSpanWithRecordedException(): void
    {
        $observer = new OtelHashingObserver($this->tracer);
        $id = 'failid';
        $error = new RuntimeException('boom');

        $observer->observe(HashObservation::started(HashOperation::HMAC_FILE, $id, 'sha256'));
        $observer->observe(HashObservation::failed($id, HashOperation::HMAC_FILE, $error));

        self::assertCount(1, $this->tracer->spans);

        $span = $this->tracer->spans[0];
        self::assertSame('HMAC_FILE', $span->name);
        self::assertSame(StatusCode::STATUS_ERROR, $span->status);
        self::assertTrue($span->ended);
        self::assertSame($error, $span->recordedException);
    }

    public function testUnknownIdCompletesSilently(): void
    {
        $observer = new OtelHashingObserver($this->tracer);

        // No matching started span: must not throw.
        $observer->observe(HashObservation::completed('orphan', 'sha256', 1, 1, null));

        self::assertCount(0, $this->tracer->spans);
    }

    public function testDefaultTracerDoesNotThrow(): void
    {
        // Without an injected tracer the observer falls back to Globals::tracerProvider();
        // driving a full lifecycle must not raise.
        $observer = new OtelHashingObserver();
        self::assertInstanceOf(HashingObserverInterface::class, $observer);
        $id = 'defaultid';

        $observer->observe(HashObservation::started(HashOperation::HASH_STREAM, $id, 'sha256'));
        $observer->observe(HashObservation::progress($id, 10));
        $observer->observe(HashObservation::completed($id, 'sha256', 10, 1, 'urn:hashit:sha256:cafe'));
    }

    public function testWiredIntoCompositionRoot(): void
    {
        $observer = new OtelHashingObserver($this->tracer);

        $hash = NativeHashIt::create($observer)->hash('observe me', 'sha256');

        self::assertCount(1, $this->tracer->spans);
        self::assertSame('HASH', $this->tracer->spans[0]->name);
        self::assertSame($hash->toUrn(), $this->tracer->spans[0]->attributes['hashit.result_urn']);
    }
}

final class SpyTracer implements TracerInterface
{
    /** @var list<SpySpan> */
    public array $spans = [];

    public function spanBuilder(string $spanName): SpanBuilderInterface
    {
        return new SpySpanBuilder($spanName, $this);
    }

    public function isEnabled(): bool
    {
        return true;
    }
}

final class SpySpanBuilder implements SpanBuilderInterface
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    public function __construct(
        private readonly string $name,
        private readonly SpyTracer $tracer,
    ) {
    }

    public function setParent(ContextInterface|false|null $context): SpanBuilderInterface
    {
        return $this;
    }

    public function addLink(SpanContextInterface $context, iterable $attributes = []): SpanBuilderInterface
    {
        return $this;
    }

    public function setAttribute(string $key, mixed $value): SpanBuilderInterface
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function setAttributes(iterable $attributes): SpanBuilderInterface
    {
        return $this;
    }

    public function setStartTimestamp(int $timestampNanos): SpanBuilderInterface
    {
        return $this;
    }

    public function setSpanKind(int $spanKind): SpanBuilderInterface
    {
        return $this;
    }

    public function startSpan(): SpanInterface
    {
        $span = new SpySpan($this->name, $this->attributes);
        $this->tracer->spans[] = $span;

        return $span;
    }
}

final class SpySpan implements SpanInterface
{
    /** @var array<string, mixed> */
    public array $attributes = [];

    public ?string $status = null;

    public bool $ended = false;

    public ?Throwable $recordedException = null;

    public function __construct(
        public readonly string $name,
        array $attributes = [],
    ) {
        $this->attributes = $attributes;
    }

    public function getContext(): SpanContextInterface
    {
        return SpanContext::getInvalid();
    }

    public function isRecording(): bool
    {
        return true;
    }

    public function setAttribute(string $key, mixed $value): SpanInterface
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function setAttributes(iterable $attributes): SpanInterface
    {
        return $this;
    }

    public function addLink(SpanContextInterface $context, iterable $attributes = []): SpanInterface
    {
        return $this;
    }

    public function addEvent(string $name, iterable $attributes = [], ?int $timestamp = null): SpanInterface
    {
        return $this;
    }

    public function recordException(Throwable $exception, iterable $attributes = []): SpanInterface
    {
        $this->recordedException = $exception;

        return $this;
    }

    public function updateName(string $name): SpanInterface
    {
        return $this;
    }

    public function setStatus(string $code, ?string $description = null): SpanInterface
    {
        $this->status = $code;

        return $this;
    }

    public function end(?int $endEpochNanos = null): void
    {
        $this->ended = true;
    }

    public function activate(): ScopeInterface
    {
        return new class () implements ScopeInterface {
            public function detach(): int
            {
                return 0;
            }
        };
    }

    public function storeInContext(ContextInterface $context): ContextInterface
    {
        return $context;
    }

    public static function fromContext(ContextInterface $context): SpanInterface
    {
        return new self('');
    }

    public static function getCurrent(): SpanInterface
    {
        return new self('');
    }

    public static function getInvalid(): SpanInterface
    {
        return new self('');
    }

    public static function wrap(SpanContextInterface $spanContext): SpanInterface
    {
        return new self('');
    }
}
