<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Http;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Http\Psr7ResponseCorrelationEnricher;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(Psr7ResponseCorrelationEnricher::class)]
final class Psr7ResponseCorrelationEnricherTest extends Unit
{
    public function testEnrichAddsHeaders(): void
    {
        $enricher = new Psr7ResponseCorrelationEnricher();
        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturn($response);

        $result = $enricher->enrich($response, 'exec-1', 'corr-1');

        self::assertSame($response, $result);
    }

    public function testEnrichOmitCorrelationWhenNull(): void
    {
        $enricher = new Psr7ResponseCorrelationEnricher();
        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturn($response);

        $enricher->enrich($response, 'exec-1');
    }
}
