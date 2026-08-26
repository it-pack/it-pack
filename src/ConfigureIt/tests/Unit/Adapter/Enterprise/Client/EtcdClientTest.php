<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Enterprise\Client;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\EtcdClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\FakeHttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\HttpResponse;
use RuntimeException;

/**
 * @covers \ItPack\ConfigureIt\Adapter\Enterprise\Client\EtcdClient
 */
final class EtcdClientTest extends Unit
{
    public function testNestingUnderPrefix(): void
    {
        $body = json_encode([
            'node' => [
                'key' => '/app',
                'nodes' => [
                    [
                        'key' => '/app/database',
                        'nodes' => [
                            ['key' => '/app/database/host', 'value' => 'db.internal'],
                            ['key' => '/app/database/port', 'value' => '5432'],
                        ],
                    ],
                    ['key' => '/app/feature/flag', 'value' => 'on'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $json = $this->client([new HttpResponse(200, $body)])->fetch('etcd://localhost:2379/app');

        verify(json_decode($json, true))->equals([
            'database' => ['host' => 'db.internal', 'port' => '5432'],
            'feature' => ['flag' => 'on'],
        ]);
    }

    public function testNodeWithoutValueOrChildrenIsIgnored(): void
    {
        $body = json_encode([
            'node' => [
                'key' => '/app',
                'nodes' => [
                    ['key' => '/app/orphan'],
                    ['key' => '/app/host', 'value' => 'h1'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $json = $this->client([new HttpResponse(200, $body)])->fetch('etcd://localhost:2379/app');

        verify(json_decode($json, true))->equals(['host' => 'h1']);
    }

    public function testNonArrayChildIsSkipped(): void
    {
        $body = json_encode([
            'node' => [
                'key' => '/app',
                'nodes' => [
                    'not-an-object',
                    ['key' => '/app/host', 'value' => 'h1'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $json = $this->client([new HttpResponse(200, $body)])->fetch('etcd://localhost:2379/app');

        verify(json_decode($json, true))->equals(['host' => 'h1']);
    }

    public function testKeyEqualToPrefixIsSkipped(): void
    {
        $body = json_encode([
            'node' => [
                'key' => '/app',
                'nodes' => [
                    ['key' => '/app', 'value' => 'root'],
                    ['key' => '/app/host', 'value' => 'h1'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $json = $this->client([new HttpResponse(200, $body)])->fetch('etcd://localhost:2379/app');

        verify(json_decode($json, true))->equals(['host' => 'h1']);
    }

    public function testEmptyPrefixRootsAtFullKey(): void
    {
        $body = json_encode([
            'node' => [
                'key' => '/',
                'nodes' => [
                    ['key' => '/database/host', 'value' => 'db.internal'],
                    ['key' => '/feature/flag', 'value' => 'on'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $json = $this->client([new HttpResponse(200, $body)])->fetch('etcd://localhost:2379');

        verify(json_decode($json, true))->equals([
            'database' => ['host' => 'db.internal'],
            'feature' => ['flag' => 'on'],
        ]);
    }

    public function testTlsSchemeUpgradesToHttps(): void
    {
        $body = json_encode([
            'node' => ['key' => '/app', 'nodes' => [['key' => '/app/host', 'value' => 'h1']]],
        ], JSON_THROW_ON_ERROR);

        $json = $this->client([new HttpResponse(200, $body)])->fetch('etcd+tls://secure:2379/app');

        verify(json_decode($json, true))->equals(['host' => 'h1']);
    }

    public function testNon200StatusThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('etcd request');

        $this->client([new HttpResponse(503, 'down')])->fetch('etcd://localhost:2379/app');
    }

    public function testMissingNodeTreeThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('had no node tree');

        $this->client([new HttpResponse(200, '{"foo":"bar"}')])->fetch('etcd://localhost:2379/app');
    }

    public function testUnparseableUriThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot parse URI');

        $this->client([])->fetch('etcd://');
    }

    public function testUnsupportedSchemeThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsupported URI scheme');

        $this->client([])->fetch('gopher://localhost:2379/app');
    }

    /**
     * @param array<int, HttpResponse> $responses
     */
    private function client(array $responses): EtcdClient
    {
        $first = $responses[0] ?? new HttpResponse(200, '{"node":{"key":"/app","nodes":[]}}');

        return new EtcdClient(new FakeHttpClient(['/v2/keys/' => $first]));
    }
}
