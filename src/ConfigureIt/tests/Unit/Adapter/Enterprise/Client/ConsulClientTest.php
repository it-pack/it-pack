<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Enterprise\Client;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\ConsulClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\FakeHttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\HttpResponse;
use RuntimeException;

/**
 * @covers \ItPack\ConfigureIt\Adapter\Enterprise\Client\ConsulClient
 */
final class ConsulClientTest extends Unit
{
    public function testNestingUnderPrefix(): void
    {
        $body = json_encode([
            ['Key' => 'app/database/host', 'Value' => base64_encode('db.internal')],
            ['Key' => 'app/database/port', 'Value' => base64_encode('5432')],
            ['Key' => 'app/feature/flag', 'Value' => base64_encode('on')],
        ], JSON_THROW_ON_ERROR);

        $json = $this->client([new HttpResponse(200, $body)])->fetch('consul://localhost:8500/app');

        verify(json_decode($json, true))->equals([
            'database' => ['host' => 'db.internal', 'port' => '5432'],
            'feature' => ['flag' => 'on'],
        ]);
    }

    public function testEntriesWithoutKeyAreSkipped(): void
    {
        $body = json_encode([
            ['Value' => base64_encode('ignored')],
            ['Key' => 'app/host', 'Value' => base64_encode('h1')],
        ], JSON_THROW_ON_ERROR);

        $json = $this->client([new HttpResponse(200, $body)])->fetch('consul://localhost:8500/app');

        verify(json_decode($json, true))->equals(['host' => 'h1']);
    }

    public function testInvalidBase64ValueDecodesToEmptyString(): void
    {
        $body = json_encode([
            ['Key' => 'app/host', 'Value' => '!!!not-base64!!!'],
        ], JSON_THROW_ON_ERROR);

        $json = $this->client([new HttpResponse(200, $body)])->fetch('consul://localhost:8500/app');

        verify(json_decode($json, true))->equals(['host' => '']);
    }

    public function testKeyEqualToPrefixIsSkipped(): void
    {
        $body = json_encode([
            ['Key' => 'app', 'Value' => base64_encode('root')],
            ['Key' => 'app/host', 'Value' => base64_encode('h1')],
        ], JSON_THROW_ON_ERROR);

        $json = $this->client([new HttpResponse(200, $body)])->fetch('consul://localhost:8500/app');

        verify(json_decode($json, true))->equals(['host' => 'h1']);
    }

    public function testEmptyPrefixRootsAtFullKey(): void
    {
        $body = json_encode([
            ['Key' => 'database/host', 'Value' => base64_encode('db.internal')],
            ['Key' => 'feature/flag', 'Value' => base64_encode('on')],
        ], JSON_THROW_ON_ERROR);

        $json = $this->client([new HttpResponse(200, $body)])->fetch('consul://localhost:8500');

        verify(json_decode($json, true))->equals([
            'database' => ['host' => 'db.internal'],
            'feature' => ['flag' => 'on'],
        ]);
    }

    public function testTlsSchemeUpgradesToHttps(): void
    {
        $body = json_encode([
            ['Key' => 'app/host', 'Value' => base64_encode('h1')],
        ], JSON_THROW_ON_ERROR);

        $json = $this->client([new HttpResponse(200, $body)])
            ->fetch('consul+tls://secure:8500/app')
        ;

        verify(json_decode($json, true))->equals(['host' => 'h1']);
    }

    public function testNon200StatusThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Consul KV request');

        $this->client([new HttpResponse(500, 'boom')])->fetch('consul://localhost:8500/app');
    }

    public function testNonListResponseThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('was not a list');

        $this->client([new HttpResponse(200, '42')])->fetch('consul://localhost:8500/app');
    }

    public function testUnparseableUriThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot parse URI');

        $this->client([])->fetch('consul://');
    }

    public function testUnsupportedSchemeThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsupported URI scheme');

        $this->client([])->fetch('ftp://localhost:8500/app');
    }

    /**
     * @param array<int, HttpResponse> $responses
     */
    private function client(array $responses): ConsulClient
    {
        $first = $responses[0] ?? new HttpResponse(200, '[]');

        return new ConsulClient(new FakeHttpClient(['/v1/kv/' => $first]));
    }
}
