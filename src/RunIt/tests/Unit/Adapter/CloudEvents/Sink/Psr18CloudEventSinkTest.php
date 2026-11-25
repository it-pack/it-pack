<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents\Sink;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSerializerInterface;
use ItPack\RunIt\Adapter\CloudEvents\Sink\Psr18CloudEventSink;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use ReflectionProperty;

#[CoversClass(Psr18CloudEventSink::class)]
final class Psr18CloudEventSinkTest extends Unit
{
    public function testPropertiesAreSet(): void
    {
        $sink = new Psr18CloudEventSink(
            $this->createMock(ClientInterface::class),
            $this->createMock(RequestFactoryInterface::class),
            $this->createMock(StreamFactoryInterface::class),
            $this->createMock(CloudEventSerializerInterface::class),
            'https://example.com/ingest',
        );

        self::assertInstanceOf(Psr18CloudEventSink::class, $sink);
    }

    public function testMethodDefaultIsPost(): void
    {
        $sink = new Psr18CloudEventSink(
            $this->createMock(ClientInterface::class),
            $this->createMock(RequestFactoryInterface::class),
            $this->createMock(StreamFactoryInterface::class),
            $this->createMock(CloudEventSerializerInterface::class),
            'https://example.com/ingest',
            'GET',
        );

        $reflection = new ReflectionProperty($sink, 'method');

        self::assertSame('GET', $reflection->getValue($sink));
    }
}
