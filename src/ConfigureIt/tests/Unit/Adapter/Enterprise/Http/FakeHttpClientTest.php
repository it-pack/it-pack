<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Enterprise\Http;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\FakeHttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\HttpResponse;
use RuntimeException;

/**
 * @covers \ItPack\ConfigureIt\Adapter\Enterprise\Http\FakeHttpClient
 */
final class FakeHttpClientTest extends Unit
{
    public function testMatchingResponseIsReturnedForUrlSubstring(): void
    {
        $http = new FakeHttpClient([
            'alpha' => new HttpResponse(200, 'a'),
            'beta' => new HttpResponse(204, 'b'),
        ]);

        verify($http->request('GET', 'https://x.test/alpha/1')->body)->equals('a');
        verify($http->request('GET', 'https://x.test/beta/2')->status)->equals(204);
    }

    public function testFirstMatchingNeedleWins(): void
    {
        $http = new FakeHttpClient([
            'app' => new HttpResponse(200, 'first'),
            'app/detail' => new HttpResponse(200, 'second'),
        ]);

        verify($http->request('GET', 'https://x.test/app/detail')->body)->equals('first');
    }

    public function testUnknownUrlThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FakeHttpClient has no response for "GET https://x.test/missing".');

        new FakeHttpClient()->request('GET', 'https://x.test/missing');
    }
}
