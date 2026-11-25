<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Http;

use Codeception\Test\Unit;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use ItPack\RunIt\Adapter\Psr\Http\Psr7RequestContextExtractor;
use ItPack\RunIt\Contract\Shared\CorrelationKeys;
use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(Psr7RequestContextExtractor::class)]
final class Psr7RequestContextExtractorTest extends Unit
{
    private Psr7RequestContextExtractor $extractor;

    protected function _before(): void
    {
        $this->extractor = new Psr7RequestContextExtractor();
    }

    public function testExtractProducesHttpExecutionContext(): void
    {
        $request = $this->request('GET', '/users/123', ['X-Correlation-Id' => 'corr-1']);

        $context = $this->extractor->extract($request);

        self::assertSame('get.users.id', (string) $context->getName());
        self::assertSame('http.request', (string) $context->getKind());
        self::assertSame('corr-1', $context->getCorrelationId());
    }

    public function testExtractWithoutCorrelationHeader(): void
    {
        $request = $this->request('POST', '/orders', []);

        $context = $this->extractor->extract($request);

        self::assertSame('post.orders', (string) $context->getName());
        self::assertNull($context->getCorrelationId());
        self::assertInstanceOf(ExecutionContext::class, $context->getContext());
    }

    public function testExtractNormalizesNumericAndUuidSegments(): void
    {
        $request = $this->request('GET', '/api/users/550e8400-e29b-41d4-a716-446655440000/posts/42', []);

        $context = $this->extractor->extract($request);

        self::assertSame('get.api.users.id.posts.id', (string) $context->getName());
    }

    public function testExtractCollapsesInvalidCharacters(): void
    {
        $request = $this->request('GET', '/a b-c!d', []);

        $context = $this->extractor->extract($request);

        self::assertSame('get.a.20b-c.d', (string) $context->getName());
    }

    public function testExtractIgnoresEmptyPathSegment(): void
    {
        $request = $this->request('GET', '/', []);

        $context = $this->extractor->extract($request);

        self::assertSame('get', (string) $context->getName());
    }

    public function testExtractWithCustomCardinalityPlaceholder(): void
    {
        $extractor = new Psr7RequestContextExtractor(
            kind: 'http.request',
            correlationHeader: CorrelationKeys::HTTP_CORRELATION_HEADER,
            cardinalityPlaceholder: 'param',
        );
        $request = $this->request('GET', '/users/123', []);

        $context = $extractor->extract($request);

        self::assertSame('get.users.param', (string) $context->getName());
    }

    public function testExtractCorrelationIdStoredInContext(): void
    {
        $request = $this->request('GET', '/items', ['X-Correlation-Id' => 'cid-1']);

        $context = $this->extractor->extract($request);

        $entry = $context->getContext()->get(CorrelationKeys::CONTEXT_CORRELATION_KEY);
        self::assertInstanceOf(ContextEntry::class, $entry);
        self::assertSame('cid-1', $entry->value());
    }

    /** @param array<string> $headers */
    private function request(string $method, string $path, array $headers): ServerRequestInterface
    {
        $uri = new Uri($path);

        return new ServerRequest($method, $uri, $headers);
    }
}
