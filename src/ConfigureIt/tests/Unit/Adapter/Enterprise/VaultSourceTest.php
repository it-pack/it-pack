<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Enterprise;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\FakeRemoteKeyValueClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\VaultClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\FakeHttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\HttpResponse;
use ItPack\ConfigureIt\Adapter\Enterprise\RemoteJsonReader;
use ItPack\ConfigureIt\Adapter\Enterprise\VaultSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;

class VaultSourceTest extends Unit
{
    public function testVaultClientUnwrapsKvV2SecretData(): void
    {
        $body = json_encode([
            'data' => [
                'data' => ['db_password' => 's3cr3t', 'api_key' => 'abc123'],
                'metadata' => ['created_time' => '2026-01-01'],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = new VaultClient(
            new FakeHttpClient(['v1/secret/data/app' => new HttpResponse(200, $body)]),
        );

        $json = $client->fetch('vault://vault.example.com:8200/secret/data/app');

        verify(json_decode($json, true))->equals([
            'db_password' => 's3cr3t',
            'api_key' => 'abc123',
        ]);
    }

    public function testVaultClientSupportsKvV1Layout(): void
    {
        $body = json_encode(['db_password' => 's3cr3t'], JSON_THROW_ON_ERROR);

        $client = new VaultClient(
            new FakeHttpClient(['v1/secret/app' => new HttpResponse(200, $body)]),
        );

        verify(json_decode($client->fetch('vault://localhost:8200/secret/app'), true))
            ->equals(['db_password' => 's3cr3t'])
        ;
    }

    public function testVaultSourceResolvesAndMarksSensitive(): void
    {
        $snapshot = new ConfigurationBuilder()
            ->addReader(new RemoteJsonReader('vault', new FakeRemoteKeyValueClient(
                json_encode(['db' => ['password' => 's3cr3t']], JSON_THROW_ON_ERROR),
            )))
            ->addSource(new VaultSource('vault.example.com', 'secret/data/app'))
            ->addLoader(new JsonLoader())
            ->build()
            ->resolve()
        ;

        verify($snapshot->get('db.password'))->equals('s3cr3t');

        $provenance = $snapshot->provenance('db.password');
        verify($provenance?->sourceKind())->equals('vault');
        verify($provenance?->isSensitive())->true();
        verify($snapshot->isSensitive('db.password'))->true();
    }
}
