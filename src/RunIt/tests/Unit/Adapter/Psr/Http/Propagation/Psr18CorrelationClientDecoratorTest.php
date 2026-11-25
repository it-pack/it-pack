<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Http\Propagation;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Contract\HeaderPropagatorInterface;
use ItPack\RunIt\Adapter\Psr\Http\Propagation\Psr18CorrelationClientDecorator;
use ItPack\RunIt\Contract\Application\CurrentExecutionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(Psr18CorrelationClientDecorator::class)]
final class Psr18CorrelationClientDecoratorTest extends Unit
{
    public function testSendRequestWithoutScopePassesThroughUnchanged(): void
    {
        $request = self::createStub(RequestInterface::class);
        $response = self::createStub(ResponseInterface::class);

        $inner = self::createStub(ClientInterface::class);
        $inner->method('sendRequest')->willReturn($response);

        $current = self::createStub(CurrentExecutionInterface::class);
        $current->method('get')->willReturn(null);

        $propagator = self::createStub(HeaderPropagatorInterface::class);

        $decorator = new Psr18CorrelationClientDecorator($inner, $propagator, $current);

        $result = $decorator->sendRequest($request);

        self::assertSame($response, $result);
    }
}
