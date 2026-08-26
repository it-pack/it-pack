<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Enterprise;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\CachingReader;
use ItPack\ConfigureIt\Adapter\Enterprise\AwsParameterStoreSource;
use ItPack\ConfigureIt\Adapter\Enterprise\AzureAppConfigSource;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\AwsSsmClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\AzureAppConfigClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\ConsulClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\EtcdClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\FakeRemoteKeyValueClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\GcpSecretManagerClient;
use ItPack\ConfigureIt\Adapter\Enterprise\ConsulSource;
use ItPack\ConfigureIt\Adapter\Enterprise\EtcdSource;
use ItPack\ConfigureIt\Adapter\Enterprise\GcpSecretManagerSource;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\FakeHttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\HttpResponse;
use ItPack\ConfigureIt\Adapter\Enterprise\HttpReader;
use ItPack\ConfigureIt\Adapter\Enterprise\HttpSource;
use ItPack\ConfigureIt\Adapter\Enterprise\RemoteJsonReader;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\RemoteDocumentCacheInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Exception\ReadException;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;
use RuntimeException;

class RemoteSourceTest extends Unit
{
    private const NESTED = '{"database":{"host":"db.internal","port":"5432"},"feature":{"flag":"on"}}';

    public function testAwsParameterStoreResolvesWithProvenance(): void
    {
        $snapshot = new ConfigurationBuilder()
            ->addReader(new RemoteJsonReader('aws-ssm', new FakeRemoteKeyValueClient(self::NESTED)))
            ->addSource(new AwsParameterStoreSource('/app'))
            ->addLoader(new JsonLoader())
            ->build()
            ->resolve()
        ;

        verify($snapshot->get('database.host'))->equals('db.internal');
        verify($snapshot->get('database.port'))->equals('5432');
        verify($snapshot->get('feature.flag'))->equals('on');

        $provenance = $snapshot->provenance('database.host');
        verify($provenance?->sourceKind())->equals('aws-ssm');
        verify(str_starts_with($provenance?->resourceUri() ?? '', 'aws-ssm://'))->true();
    }

    public function testAzureAppConfigResolvesWithProvenance(): void
    {
        $snapshot = new ConfigurationBuilder()
            ->addReader(new RemoteJsonReader('azure-appconfig', new FakeRemoteKeyValueClient(self::NESTED)))
            ->addSource(new AzureAppConfigSource('mystore', 'app'))
            ->addLoader(new JsonLoader())
            ->build()
            ->resolve()
        ;

        verify($snapshot->get('database.host'))->equals('db.internal');
        verify($snapshot->provenance('feature.flag')?->sourceKind())->equals('azure-appconfig');
    }

    public function testGcpSecretManagerResolvesWithProvenance(): void
    {
        $snapshot = new ConfigurationBuilder()
            ->addReader(new RemoteJsonReader('gcp-secretmanager', new FakeRemoteKeyValueClient(self::NESTED)))
            ->addSource(new GcpSecretManagerSource('my-project', 'app'))
            ->addLoader(new JsonLoader())
            ->build()
            ->resolve()
        ;

        verify($snapshot->get('database.host'))->equals('db.internal');
        verify($snapshot->provenance('database.port')?->sourceKind())->equals('gcp-secretmanager');
    }

    public function testAwsSsmClientNestsParametersByPath(): void
    {
        $body = json_encode([
            'Parameters' => [
                ['Name' => '/app/database/host', 'Value' => 'db.internal'],
                ['Name' => '/app/database/port', 'Value' => '5432'],
                ['Name' => '/app/feature/flag', 'Value' => 'on'],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = new AwsSsmClient(
            new FakeHttpClient(['amazonaws.com' => new HttpResponse(200, $body)]),
            'eu-west-1',
        );

        $json = $client->fetch('aws-ssm:///app');

        verify(json_decode($json, true))->equals([
            'app' => [
                'database' => ['host' => 'db.internal', 'port' => '5432'],
                'feature' => ['flag' => 'on'],
            ],
        ]);
    }

    public function testAzureAppConfigClientNestsByColon(): void
    {
        $body = json_encode([
            'items' => [
                ['key' => 'app:database:host', 'value' => 'db.internal'],
                ['key' => 'app:feature:flag', 'value' => 'on'],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = new AzureAppConfigClient(
            new FakeHttpClient(['azconfig.io' => new HttpResponse(200, $body)]),
        );

        $json = $client->fetch('azure-appconfig://mystore/app');

        verify(json_decode($json, true))->equals([
            'app' => [
                'database' => ['host' => 'db.internal'],
                'feature' => ['flag' => 'on'],
            ],
        ]);
    }

    public function testGcpSecretManagerClientNestsByDash(): void
    {
        $list = json_encode([
            'secrets' => [
                ['name' => 'projects/p/secrets/app-database-host'],
                ['name' => 'projects/p/secrets/app-feature-flag'],
                ['name' => 'projects/p/secrets/other-ignored'],
            ],
        ], JSON_THROW_ON_ERROR);

        $accessHost = json_encode(['payload' => ['data' => base64_encode('db.internal')]], JSON_THROW_ON_ERROR);
        $accessFlag = json_encode(['payload' => ['data' => base64_encode('on')]], JSON_THROW_ON_ERROR);
        $accessOther = json_encode(['payload' => ['data' => base64_encode('x')]], JSON_THROW_ON_ERROR);

        $http = new FakeHttpClient([
            'v1/projects/p/secrets' => new HttpResponse(200, $list),
            'app-database-host:access' => new HttpResponse(200, $accessHost),
            'app-feature-flag:access' => new HttpResponse(200, $accessFlag),
            'other-ignored:access' => new HttpResponse(200, $accessOther),
        ]);

        $client = new GcpSecretManagerClient($http);

        $json = $client->fetch('gcp-secretmanager://p/app');

        verify(json_decode($json, true))->equals([
            'app' => [
                'database' => ['host' => 'db.internal'],
                'feature' => ['flag' => 'on'],
            ],
        ]);
    }

    // --- M5 — Epic E6.1: Remote sources (Consul / etcd / HTTP) + CachingReader ---

    public function testHttpSourceResolvesFromJsonEndpoint(): void
    {
        $snapshot = new ConfigurationBuilder()
            ->addReader(new HttpReader(new FakeHttpClient(['config.acme.test' => new HttpResponse(200, self::NESTED)])))
            ->addSource(new HttpSource('http://config.acme.test/app'))
            ->addLoader(new JsonLoader())
            ->build()
            ->resolve()
        ;

        verify($snapshot->get('database.host'))->equals('db.internal');
        verify($snapshot->get('feature.flag'))->equals('on');
        verify($snapshot->provenance('database.port')?->sourceKind())->equals('http');
        verify(str_starts_with($snapshot->provenance('database.port')?->resourceUri() ?? '', 'http://'))->true();
    }

    public function testConsulClientNestsUnderPrefix(): void
    {
        $body = json_encode([
            ['Key' => 'app/database/host', 'Value' => base64_encode('db.internal')],
            ['Key' => 'app/database/port', 'Value' => base64_encode('5432')],
            ['Key' => 'app/feature/flag', 'Value' => base64_encode('on')],
        ], JSON_THROW_ON_ERROR);

        $client = new ConsulClient(
            new FakeHttpClient(['/v1/kv/app' => new HttpResponse(200, $body)]),
        );

        $json = $client->fetch('consul://localhost:8500/app');

        verify(json_decode($json, true))->equals([
            'database' => ['host' => 'db.internal', 'port' => '5432'],
            'feature' => ['flag' => 'on'],
        ]);
    }

    public function testEtcdClientNestsUnderPrefix(): void
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
                    [
                        'key' => '/app/feature',
                        'nodes' => [
                            ['key' => '/app/feature/flag', 'value' => 'on'],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = new EtcdClient(
            new FakeHttpClient(['/v2/keys/app' => new HttpResponse(200, $body)]),
        );

        $json = $client->fetch('etcd://localhost:2379/app');

        verify(json_decode($json, true))->equals([
            'database' => ['host' => 'db.internal', 'port' => '5432'],
            'feature' => ['flag' => 'on'],
        ]);
    }

    public function testConsulClientUpgradesTlsUrisToHttps(): void
    {
        $body = json_encode([
            ['Key' => 'app/database/host', 'Value' => base64_encode('db.internal')],
        ], JSON_THROW_ON_ERROR);

        $client = new ConsulClient(
            new FakeHttpClient(['https://secure:8500/v1/kv/app' => new HttpResponse(200, $body)]),
        );

        $json = $client->fetch('consul+tls://secure:8500/app');

        verify(json_decode($json, true))->equals(['database' => ['host' => 'db.internal']]);
    }

    public function testConsulClientRejectsUnknownSchemes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsupported URI scheme');

        new ConsulClient(new FakeHttpClient())->fetch('ftp://localhost:8500/app');
    }

    public function testEtcdClientUpgradesTlsUrisToHttps(): void
    {
        $body = json_encode([
            'node' => [
                'key' => '/app',
                'nodes' => [
                    ['key' => '/app/feature/flag', 'value' => 'on'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = new EtcdClient(
            new FakeHttpClient(['https://secure:2379/v2/keys/app' => new HttpResponse(200, $body)]),
        );

        $json = $client->fetch('etcd+tls://secure:2379/app');

        verify(json_decode($json, true))->equals(['feature' => ['flag' => 'on']]);
    }

    public function testEtcdClientRejectsUnknownSchemes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsupported URI scheme');

        new EtcdClient(new FakeHttpClient())->fetch('gopher://localhost:2379/app');
    }

    public function testConsulSourceResolvesWithConsulClient(): void
    {
        $body = json_encode([
            ['Key' => 'app/database/host', 'Value' => base64_encode('db.internal')],
            ['Key' => 'app/feature/flag', 'Value' => base64_encode('on')],
        ], JSON_THROW_ON_ERROR);

        $snapshot = new ConfigurationBuilder()
            ->addReader(new RemoteJsonReader('consul', new ConsulClient(
                new FakeHttpClient(['/v1/kv/app' => new HttpResponse(200, $body)]),
            )))
            ->addSource(new ConsulSource('localhost', 8500, 'app'))
            ->addLoader(new JsonLoader())
            ->build()
            ->resolve()
        ;

        verify($snapshot->get('database.host'))->equals('db.internal');
        verify($snapshot->get('feature.flag'))->equals('on');
        verify($snapshot->provenance('database.host')?->sourceKind())->equals('consul');
    }

    public function testEtcdSourceResolvesWithEtcdClient(): void
    {
        $body = json_encode([
            'node' => [
                'key' => '/app',
                'nodes' => [
                    ['key' => '/app/feature/flag', 'value' => 'on'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $snapshot = new ConfigurationBuilder()
            ->addReader(new RemoteJsonReader('etcd', new EtcdClient(
                new FakeHttpClient(['/v2/keys/app' => new HttpResponse(200, $body)]),
            )))
            ->addSource(new EtcdSource('localhost', 2379, 'app'))
            ->addLoader(new JsonLoader())
            ->build()
            ->resolve()
        ;

        verify($snapshot->get('feature.flag'))->equals('on');
        verify($snapshot->provenance('feature.flag')?->sourceKind())->equals('etcd');
    }

    public function testCachingReaderServesCachedAcrossReads(): void
    {
        $doc = '{"a":"1"}';

        $inner = new class ($doc) implements ConfigurationReaderInterface {
            private int $calls = 0;

            public function __construct(private string $doc)
            {
            }

            public function read(ConfigurationResourceInterface $resource): string
            {
                $this->calls++;

                if ($this->calls > 1) {
                    throw new ReadException('simulated outage on second fetch');
                }

                return $this->doc;
            }

            public function supports(ConfigurationResourceInterface $resource): bool
            {
                return true;
            }
        };

        $reader = new CachingReader($inner, new InMemoryDocCache(), 60);
        $resource = new ConfigurationResource('http://x/a', Format::JSON);

        $first = $reader->read($resource);
        $second = $reader->read($resource);

        verify($first)->equals($doc);
        verify($second)->equals($doc);
    }

    public function testCachingReaderFallsBackToLastGoodOnFailure(): void
    {
        $doc = '{"a":"1"}';

        $inner = new class () implements ConfigurationReaderInterface {
            public function read(ConfigurationResourceInterface $resource): string
            {
                throw new ReadException('backend is down');
            }

            public function supports(ConfigurationResourceInterface $resource): bool
            {
                return true;
            }
        };

        $cache = new InMemoryDocCache();
        $cache->set('JSON:http://x/a', $doc, 60);
        $cache->expire('JSON:http://x/a');

        $reader = new CachingReader($inner, $cache, 60);
        $resource = new ConfigurationResource('http://x/a', Format::JSON);

        verify($reader->read($resource))->equals($doc);
    }

    public function testCachingReaderPropagatesWhenNoLastGood(): void
    {
        $inner = new class () implements ConfigurationReaderInterface {
            public function read(ConfigurationResourceInterface $resource): string
            {
                throw new ReadException('backend is down');
            }

            public function supports(ConfigurationResourceInterface $resource): bool
            {
                return true;
            }
        };

        $reader = new CachingReader($inner, new InMemoryDocCache(), 60);
        $resource = new ConfigurationResource('http://x/a', Format::JSON);

        $this->expectException(ReadException::class);

        $reader->read($resource);
    }
}

/**
 * In-memory {@see RemoteDocumentCacheInterface} for unit tests — stores the raw
 * document with an optional expiry that the test can force-expiry via
 * {@see self::expire()}.
 */
final class InMemoryDocCache implements RemoteDocumentCacheInterface
{
    /**
     * @var array<string, array{expires: ?int, document: string}>
     */
    private array $store = [];

    public function get(string $key): ?string
    {
        if (!isset($this->store[$key])) {
            return null;
        }

        $expires = $this->store[$key]['expires'];

        if (null !== $expires && $expires < time()) {
            return null;
        }

        return $this->store[$key]['document'];
    }

    public function getLastGood(string $key): ?string
    {
        return $this->store[$key]['document'] ?? null;
    }

    public function set(string $key, string $document, ?int $ttlSeconds = null): void
    {
        $this->store[$key] = [
            'expires' => null === $ttlSeconds ? null : time() + $ttlSeconds,
            'document' => $document,
        ];
    }

    public function expire(string $key): void
    {
        if (isset($this->store[$key])) {
            $this->store[$key]['expires'] = time() - 1;
        }
    }
}
