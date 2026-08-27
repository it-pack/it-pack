<?php

declare(strict_types=1);

use ItPack\ConfigureIt\Adapter\CachingReader;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\ConsulClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\EtcdClient;
use ItPack\ConfigureIt\Adapter\Enterprise\ConsulSource;
use ItPack\ConfigureIt\Adapter\Enterprise\EtcdSource;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\CurlHttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\FakeHttpClient;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\HttpResponse;
use ItPack\ConfigureIt\Adapter\Enterprise\HttpReader;
use ItPack\ConfigureIt\Adapter\Enterprise\HttpSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Cache\FileSystemDocumentCache;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Exception\ReadException;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * M5 — Epic E6.1 (Remote & Distributed sources + CachingReader) example.
 *
 * Demonstrates the three first-class dynamic backends and the resilience
 * decorator:
 *   - HttpSource        : a custom HTTP(S) JSON endpoint
 *   - ConsulSource      : HashiCorp Consul KV (prefix)
 *   - EtcdSource        : etcd v2 keys (prefix)
 *   - CachingReader     : hash+TTL cache that serves the last-good document on
 *                         a fetch failure (so a backend outage never takes down
 *                         configuration resolution).
 *
 * The runnable part below uses a {@see FakeHttpClient} so it works offline;
 * production wiring with a real transport is shown in comments.
 *
 * Run from the package root:
 *   php examples/remote.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// ---------------------------------------------------------------------------
// 1) Generic HTTP(S) JSON endpoint, with caching + last-good fallback.
// ---------------------------------------------------------------------------

$json = (string) json_encode([
    'database' => ['host' => 'db.internal', 'port' => 5432],
    'feature' => ['flag' => 'on'],
], JSON_THROW_ON_ERROR);

$httpTransport = new FakeHttpClient([
    'config.acme.test' => new HttpResponse(200, $json),
]);

// Production: $httpTransport = new CurlHttpClient();  // + signing/headers
$cacheDir = sys_get_temp_dir() . '/configure-it-remote-cache';

$resolver = \ItPack\ConfigureIt\Wiring\Configurations::builder()
    ->addReader(new CachingReader(
        new HttpReader($httpTransport),
        new FileSystemDocumentCache($cacheDir),
        60,
    ))
    ->addSource(new HttpSource('http://config.acme.test/app'))
    ->addLoader(new JsonLoader())
    ->build()
;

$snapshot = $resolver->resolve();

echo 'HttpSource -> database.host = ' . var_export($snapshot->get('database.host'), true) . PHP_EOL;
echo 'HttpSource -> feature.flag  = ' . var_export($snapshot->get('feature.flag'), true) . PHP_EOL;
echo 'HttpSource -> sourceKind    = ' . var_export($snapshot->provenance('database.host')?->sourceKind(), true) . PHP_EOL;

// --- Fallback demonstration: backend is now down, but the cache has a copy. ---
$downTransport = new FakeHttpClient([]); // no matching response -> ReadException

$fallbackReader = new CachingReader(
    new HttpReader($downTransport),
    new FileSystemDocumentCache($cacheDir),
    60,
);

$resource = new ConfigurationResource('http://config.acme.test/app', Format::JSON);
$served = $fallbackReader->read($resource);

echo 'Fallback  -> served last-good = ' . $served . PHP_EOL;

// ---------------------------------------------------------------------------
// 2) Consul KV + etcd v2 — production wiring (would run against a live agent).
// ---------------------------------------------------------------------------

// $consul = \ItPack\ConfigureIt\Wiring\Configurations::builder()
//     ->addReader(new RemoteJsonReader('consul', new ConsulClient(new CurlHttpClient())))
//     ->addSource(new ConsulSource('consul.svc', 8500, 'app'))
//     ->addLoader(new JsonLoader())
//     ->build();
//
// $etcd = \ItPack\ConfigureIt\Wiring\Configurations::builder()
//     ->addReader(new RemoteJsonReader('etcd', new EtcdClient(new CurlHttpClient())))
//     ->addSource(new EtcdSource('etcd.svc', 2379, 'app'))
//     ->addLoader(new JsonLoader())
//     ->build();
