<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Enterprise;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\FakeHttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\HttpResponse;
use ItPack\ConfigureIt\Adapter\Enterprise\HttpReader;
use ItPack\ConfigureIt\Exception\ReadException;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * @covers \ItPack\ConfigureIt\Adapter\Enterprise\HttpReader
 */
final class HttpReaderTest extends Unit
{
    public function testSupportsJsonOverHttpAndHttps(): void
    {
        $reader = new HttpReader(new FakeHttpClient());

        verify($reader->supports(new ConfigurationResource('http://x/a', Format::JSON)))->true();
        verify($reader->supports(new ConfigurationResource('https://x/a', Format::JSON)))->true();
    }

    public function testRejectsNonJsonAndNonHttp(): void
    {
        $reader = new HttpReader(new FakeHttpClient());

        verify($reader->supports(new ConfigurationResource('http://x/a', Format::YAML)))->false();
        verify($reader->supports(new ConfigurationResource('file:///x/a', Format::JSON)))->false();
    }

    public function testReadReturnsBodyOnSuccess(): void
    {
        $reader = new HttpReader(
            new FakeHttpClient(['x' => new HttpResponse(200, '{"ok":true}')]),
            ['Authorization' => 'Bearer t'],
        );

        $body = $reader->read(new ConfigurationResource('http://x/a', Format::JSON));

        verify($body)->equals('{"ok":true}');
    }

    public function testReadThrowsOnNonSuccessStatus(): void
    {
        $reader = new HttpReader(new FakeHttpClient(['x' => new HttpResponse(500, 'boom')]));

        $this->expectException(ReadException::class);
        $this->expectExceptionMessage('HTTP GET "http://x/a" failed: 500.');

        $reader->read(new ConfigurationResource('http://x/a', Format::JSON));
    }

    public function testReadThrowsWhenUnsupported(): void
    {
        $reader = new HttpReader(new FakeHttpClient());

        $this->expectException(ReadException::class);
        $this->expectExceptionMessage('HttpReader cannot read resource');

        $reader->read(new ConfigurationResource('file:///x/a', Format::JSON));
    }
}
