<?php

declare(strict_types=1);

use ItPack\ConfigureIt\Adapter\Decryption\DecryptingReader;
use ItPack\ConfigureIt\Adapter\Enterprise\KubernetesSecretSource;
use ItPack\ConfigureIt\Adapter\Enterprise\VolumeReader;
use ItPack\ConfigureIt\Adapter\EnvLoader;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\CLI\ConfigurationInspector;
use ItPack\ConfigureIt\Contract\DecrypterInterface;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;
use ItPack\ConfigureIt\ValueObject\SourceKind;

/**
 * M4 — Epic E5.1 / E5.2 (Secrets & Redaction) example.
 *
 * Demonstrates three things:
 *   1. Secret sources (Kubernetes/Docker secret volumes, Vault, cloud secret
 *      managers) auto-flag resolved values as `sensitive` via the
 *      `SecretSourceInterface` + `DocumentNormalizer` scheme detection.
 *   2. The central `RedactingRenderer` masks sensitive values on every render
 *      surface (inspector, diff, graph, telemetry) — `get()` still returns the
 *      real value, but nothing prints it in clear text.
 *   3. `DecryptingReader` transparently decrypts `enc:`-annotated resources
 *      before the loader parses them.
 *
 * Run from the package root:
 *   php examples/secrets.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Render a snapshot the same way the CLI `explain` command does (masking
 * sensitive values through the central renderer).
 */
function renderSnapshot(\ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface $snapshot): string
{
    $nodes = [];

    foreach ($snapshot->all() as $key => $value) {
        $provenance = $snapshot->provenance($key);
        $nodes[] = new ConfigurationNode(
            $key,
            $value,
            $provenance?->resourceUri() ?? 'unknown',
            $provenance?->priority() ?? 0,
            [],
            $provenance ?? new Provenance('unknown', Format::JSON, 'loader', 0, SourceKind::File),
        );
    }

    return (new ConfigurationInspector())->explain(new ConfigurationGraph($nodes));
}

// ---------------------------------------------------------------------------
// 1 + 2 — secret source marks sensitive, and rendering masks it.
// ---------------------------------------------------------------------------
$secrets = (new ConfigurationBuilder())
    ->addReader(new VolumeReader())
    ->addSource(new KubernetesSecretSource(__DIR__ . '/Support/Secrets'))
    ->addLoader(new EnvLoader())
    ->build()
    ->resolve();

echo 'db.password isSensitive = ' . var_export($secrets->isSensitive('db_password'), true) . PHP_EOL;
echo 'db.password real value  = ' . $secrets->get('db_password') . '  (get() is never masked)' . PHP_EOL;

echo PHP_EOL . '--- inspector (secrets masked) ---' . PHP_EOL;
echo renderSnapshot($secrets);

// ---------------------------------------------------------------------------
// 3 — transparent decryption of an `enc:`-annotated resource.
// ---------------------------------------------------------------------------
$dir = sys_get_temp_dir() . '/configure-it-example-secrets';
is_dir($dir) or mkdir($dir);
// `enc:` followed by a base64 blob; our demo decrypter base64-decodes it.
file_put_contents($dir . '/config.json', 'enc:' . base64_encode('{"feature":{"flag":"on"}}'));

/**
 * Demo decrypter: base64-decodes the ciphertext. A real deployment would wire
 * `AgeDecrypter` / `SopsDecrypter` (which shell out to `age` / `sops` through a
 * `CommandRunnerInterface`) or a KMS-backed adapter — all behind the same
 * `DecrypterInterface`.
 */
$demoDecrypter = new class implements DecrypterInterface {
    public function decrypt(string $ciphertext): string
    {
        $decoded = base64_decode($ciphertext, true);

        return $decoded === false ? $ciphertext : $decoded;
    }
};

$resolver = (new ConfigurationBuilder())
    ->addReader(new FileReader())
    ->addSource(new FileSource($dir))
    ->addLoader(new JsonLoader())
    ->withDecrypter($demoDecrypter)
    ->build();

$decrypted = $resolver->resolve();

echo PHP_EOL . '--- decrypted resource ---' . PHP_EOL;
echo 'feature.flag = ' . var_export($decrypted->get('feature.flag'), true) . PHP_EOL;
echo 'sourceKind   = ' . $decrypted->provenance('feature.flag')?->sourceKind() . PHP_EOL;
