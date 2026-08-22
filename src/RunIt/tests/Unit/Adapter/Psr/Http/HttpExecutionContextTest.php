<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Http;

use ItPack\RunIt\Adapter\Psr\Http\HttpExecutionContext;
use ItPack\RunIt\Adapter\Psr\Http\Psr7RequestContextExtractor;
use ItPack\RunIt\Contract\Shared\HttpExecutionContextInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class HttpExecutionContextTest extends TestCase
{
    public function testClassSatisfiesTheSharedContract(): void
    {
        $context = new Psr7RequestContextExtractor()->extract(
            new ServerRequest('GET', '/users/123', ['X-Correlation-Id' => ['corr-xyz']]),
        );

        self::assertInstanceOf(HttpExecutionContext::class, $context);
        self::assertInstanceOf(HttpExecutionContextInterface::class, $context);
    }

    public function testAccessorsExposeTheExtractedInputs(): void
    {
        $context = new Psr7RequestContextExtractor()->extract(
            new ServerRequest('GET', '/users/123', ['X-Correlation-Id' => ['corr-xyz']]),
        );

        self::assertSame('get.users.id', $context->getName()->toString());
        self::assertSame('http.request', $context->getKind()->toString());
        self::assertSame('corr-xyz', $context->getCorrelationId());
        self::assertTrue($context->getContext()->has('ctx.correlation.id'));
        self::assertSame([], $context->getAttributes());
    }
}
