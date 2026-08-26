<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Http;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Http\ExecutionMiddleware;
use ItPack\RunIt\Adapter\Psr\Http\Propagation\DefaultHeaderPropagator;
use ItPack\RunIt\Adapter\Psr\Http\Propagation\Psr18CorrelationClientDecorator;
use ItPack\RunIt\Adapter\Psr\Http\Psr7RequestContextExtractor;
use ItPack\RunIt\Adapter\Psr\Http\Psr7ResponseCorrelationEnricher;
use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ContextKey;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\Instant;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Tests\Support\Fixture\Psr\FakeCurrentExecution;
use Tests\Support\Fixture\Psr\FakeExecutionRunner;
use Tests\Support\Fixture\Psr\FakeExecutionScope;
use Tests\Support\Fixture\Psr\RecordingHttpClient;
use Tests\Support\Fixture\Psr\RecordingObservationRecorder;

final class HttpAdaptersTest extends Unit
{
    public function testExtractorDerivesCardinalitySafeNameAndCorrelation(): void
    {
        $extractor = new Psr7RequestContextExtractor();
        $request = new ServerRequest('GET', '/users/123/orders', ['X-Correlation-Id' => ['corr-xyz']]);

        $context = $extractor->extract($request);

        self::assertSame('get.users.id.orders', $context->getName()->toString());
        self::assertSame('http.request', $context->getKind()->toString());
        self::assertSame('corr-xyz', $context->getCorrelationId());
        self::assertTrue($context->getContext()->has('ctx.correlation.id'));
    }

    public function testExtractorReplacesUuidSegments(): void
    {
        $extractor = new Psr7RequestContextExtractor();
        $request = new ServerRequest('POST', '/tenants/3f2504e0-4f89-41d3-9a0c-0305e82c3301/items');

        self::assertSame('post.tenants.id.items', $extractor->extract($request)->getName()->toString());
    }

    public function testEnricherAddsCorrelationHeaders(): void
    {
        $enricher = new Psr7ResponseCorrelationEnricher();
        $response = $enricher->enrich(new Response(200), 'exec-1', 'corr-1');

        self::assertSame('exec-1', $response->getHeaderLine('X-Execution-Id'));
        self::assertSame('corr-1', $response->getHeaderLine('X-Correlation-Id'));
    }

    public function testPropagatorEmitsOnlyExecutionHeaderWithoutCorrelation(): void
    {
        $propagator = new DefaultHeaderPropagator();

        $headers = $propagator->headers('exec-1');

        self::assertSame(['X-Execution-Id' => 'exec-1'], $headers);
    }

    public function testClientDecoratorInjectsCorrelationHeadersWhenExecutionActive(): void
    {
        $scope = $this->scopeWithCorrelation('corr-9');
        $recorded = new RecordingHttpClient(new Response(200));
        $client = new Psr18CorrelationClientDecorator(
            $recorded,
            new DefaultHeaderPropagator(),
            new FakeCurrentExecution($scope),
        );

        $client->sendRequest(new Request('GET', 'https://api.example/'));

        self::assertSame('fake-exec', $recorded->lastHeaders()['X-Execution-Id']);
        self::assertSame('corr-9', $recorded->lastHeaders()['X-Correlation-Id']);
    }

    private function scopeWithCorrelation(string $correlationId): FakeExecutionScope
    {
        $execution = Execution::start(
            ExecutionId::fromString('fake-exec'),
            ExecutionDefinition::create(ExecutionName::fromString('http.request'), ExecutionKind::fromString('http')),
            Instant::now(),
        );

        $context = ExecutionContext::empty()->with(
            ContextEntry::public(ContextKey::fromString('ctx.correlation.id'), $correlationId),
        );

        return new FakeExecutionScope($execution, $context, new RecordingObservationRecorder());
    }

    public function testClientDecoratorPassesThroughWithoutActiveExecution(): void
    {
        $recorded = new RecordingHttpClient(new Response(200));
        $client = new Psr18CorrelationClientDecorator(
            $recorded,
            new DefaultHeaderPropagator(),
            new FakeCurrentExecution(null),
        );

        $client->sendRequest(new Request('GET', 'https://api.example/'));

        self::assertArrayNotHasKey('X-Execution-Id', $recorded->lastHeaders());
    }

    public function testMiddlewareCreatesExecutionAndEnrichesResponse(): void
    {
        $runner = new FakeExecutionRunner();
        $handler = $this->handler(200);
        $middleware = new ExecutionMiddleware(
            $runner,
            new Psr7RequestContextExtractor(),
            new Psr7ResponseCorrelationEnricher()
        );

        $request = new ServerRequest('GET', '/users/42', ['X-Correlation-Id' => ['corr-xyz']]);
        $response = $middleware->process($request, $handler);

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame('fake-0', $response->getHeaderLine('X-Execution-Id'));
        self::assertSame('corr-xyz', $response->getHeaderLine('X-Correlation-Id'));
        self::assertSame('get.users.id', $runner->lastDefinition()?->name()->toString());
        self::assertCount(0, $runner->lastObservations()?->recorded() ?? []);
    }

    public function testMiddlewareRecordsServerErrorObservation(): void
    {
        $runner = new FakeExecutionRunner();
        $middleware = new ExecutionMiddleware(
            $runner,
            new Psr7RequestContextExtractor(),
            new Psr7ResponseCorrelationEnricher()
        );

        $middleware->process(new ServerRequest('GET', '/boom'), $this->handler(500));

        $recorded = $runner->lastObservations()?->recorded() ?? [];
        self::assertSame('http.response.server_error', $recorded[0]['message']);
        self::assertSame(ObservationSeverity::Error->label(), $recorded[0]['severity']?->label());
    }

    public function testMiddlewareRecordsClientErrorObservation(): void
    {
        $runner = new FakeExecutionRunner();
        $middleware = new ExecutionMiddleware(
            $runner,
            new Psr7RequestContextExtractor(),
            new Psr7ResponseCorrelationEnricher()
        );

        $middleware->process(new ServerRequest('GET', '/missing'), $this->handler(404));

        $recorded = $runner->lastObservations()?->recorded() ?? [];
        self::assertSame('http.response.client_error', $recorded[0]['message']);
        self::assertSame(ObservationSeverity::Warning->label(), $recorded[0]['severity']?->label());
    }

    public function testMiddlewareRecordsRequestFailureAndRethrows(): void
    {
        $runner = new FakeExecutionRunner();
        $middleware = new ExecutionMiddleware(
            $runner,
            new Psr7RequestContextExtractor(),
            new Psr7ResponseCorrelationEnricher()
        );

        $this->expectException(RuntimeException::class);

        try {
            $middleware->process(new ServerRequest('GET', '/fail'), $this->handler(200, true));
        } finally {
            $recorded = $runner->lastObservations()?->recorded() ?? [];
            self::assertSame('http.request.failed', $recorded[0]['message'] ?? null);
            self::assertSame(ObservationSeverity::Error->label(), $recorded[0]['severity']?->label());
        }
    }

    public function testMalformedOrEmptyCorrelationHeaderYieldsNoCorrelation(): void
    {
        $extractor = new Psr7RequestContextExtractor();

        $empty = $extractor->extract(new ServerRequest('GET', '/users/1', ['X-Correlation-Id' => ['']]));
        self::assertNull($empty->getCorrelationId());
        self::assertFalse($empty->getContext()->has('ctx.correlation.id'));

        $whitespace = $extractor->extract(new ServerRequest('GET', '/users/1', ['X-Correlation-Id' => ['   ']]));
        self::assertNull($whitespace->getCorrelationId());
        self::assertFalse($whitespace->getContext()->has('ctx.correlation.id'));

        // A name built from an unparseable path still collapses to a valid name.
        $odd = $extractor->extract(new ServerRequest('GET', '//%%/users//'));
        self::assertMatchesRegularExpression('/^get\.[a-z0-9._-]+$/', $odd->getName()->toString());
    }

    public function testCorrelationValueCannotInjectExtraHeaders(): void
    {
        $crlf = "corr-1\r\nInjected: evil";

        // The propagator only ever emits the configured header names, so an
        // attacker-controlled correlation value can never become a header name.
        $headers = new DefaultHeaderPropagator()->headers('exec-1', $crlf);
        self::assertSame(['X-Execution-Id' => 'exec-1', 'X-Correlation-Id' => $crlf], $headers);

        // PSR-7 rejects the CRLF value outright, so the enricher uses a value that
        // PSR-7 accepts but would be dangerous if naively concatenated into a raw
        // header line. The comma-embedded fake header must stay a single value.
        $malicious = 'corr-1, X-Fake: evil';
        $response = new Psr7ResponseCorrelationEnricher()->enrich(new Response(200), 'exec-1', $malicious);
        $names = array_keys($response->getHeaders());

        self::assertContains('X-Execution-Id', $names);
        self::assertContains('X-Correlation-Id', $names);
        self::assertNotContains('X-Fake', $names);
        self::assertSame($malicious, $response->getHeaderLine('X-Correlation-Id'));
    }

    private function handler(int $status, bool $throw = false): RequestHandlerInterface
    {
        return new class ($status, $throw) implements RequestHandlerInterface {
            public function __construct(private int $status, private bool $throw)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                if ($this->throw) {
                    throw new RuntimeException('handler failed');
                }

                return new Response($this->status);
            }
        };
    }
}
