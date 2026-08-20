<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Enterprise;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Enterprise\AwsParameterStoreSource;
use ItPack\ConfigureIt\Adapter\Enterprise\AzureAppConfigSource;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\AwsSsmClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\AzureAppConfigClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\FakeRemoteKeyValueClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\GcpSecretManagerClient;
use ItPack\ConfigureIt\Adapter\Enterprise\GcpSecretManagerSource;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\FakeHttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\HttpResponse;
use ItPack\ConfigureIt\Adapter\Enterprise\RemoteJsonReader;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;

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
}
