<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — publication health reporting default sink.
 *
 * Phase 4 feature: an omitted or `null` publication-failure handler reports to
 * the replaceable SDK error-log default instead of being silent, including when
 * a `DefaultExecutionRunner` is constructed through `RunItBuilder`. Explicit
 * silence is opted into with a no-op closure. Diagnostics are sanitized — no
 * artifact identifiers or raw exception messages reach the error log.
 *
 * This example shows:
 *   1. Default (no handler): a throwing publisher is reported to the error log;
 *      the business result is still returned unchanged.
 *   2. Custom handler: the reporter receives the publication failure directly.
 *   3. Explicit silence: a no-op closure reports nothing.
 *
 * Run from the package root: `php examples/health-reporting-default.php`
 */

require __DIR__ . '/../vendor/autoload.php';

final class ThrowingPublisher implements ArtifactPublisherInterface
{
    public function publish(ExecutionArtifactInterface $artifact): void
    {
        throw new RuntimeException('transport unavailable');
    }
}

$publisher = new ThrowingPublisher();
$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('operation'),
);

$defaultLog = (string) tempnam(sys_get_temp_dir(), 'runit-health-default');
$silentLog = (string) tempnam(sys_get_temp_dir(), 'runit-health-silent');

try {
    // --- 1. Default: no handler → SDK error-log default sink ---
    ini_set('error_log', $defaultLog);
    @unlink($defaultLog);

    $defaultRunner = RunItBuilder::create()
        ->withPublisher($publisher)
        ->withClock(new SystemMonotonicClock())
        ->withSequenceAllocator(new InMemorySequenceAllocator())
        ->withSnapshotSerializer(new JsonSnapshotSerializer())
        ->withIdentifierGenerator(new RandomIdentifierGenerator())
        ->build()
    ;

    $result = $defaultRunner->run($definition('health.default'), static fn (): string => 'business-value');
    $defaultLogContent = (string) @file_get_contents($defaultLog);

    // --- 2. Custom handler → receives the failure directly ---
    $captured = null;
    $customRunner = RunItBuilder::create()
        ->withPublisher($publisher)
        ->withClock(new SystemMonotonicClock())
        ->withSequenceAllocator(new InMemorySequenceAllocator())
        ->withSnapshotSerializer(new JsonSnapshotSerializer())
        ->withIdentifierGenerator(new RandomIdentifierGenerator())
        ->withPublicationFailureHandler(static function (Throwable $failure) use (&$captured): void {
            $captured = $failure;
        })
        ->build()
    ;

    $customResult = $customRunner->run($definition('health.custom'), static fn (): string => 'business-value');

    // --- 3. Explicit silence → no-op closure reports nothing ---
    ini_set('error_log', $silentLog);
    @unlink($silentLog);

    $silentRunner = RunItBuilder::create()
        ->withPublisher($publisher)
        ->withClock(new SystemMonotonicClock())
        ->withSequenceAllocator(new InMemorySequenceAllocator())
        ->withSnapshotSerializer(new JsonSnapshotSerializer())
        ->withIdentifierGenerator(new RandomIdentifierGenerator())
        ->withPublicationFailureHandler(static function (Throwable $e): void {
            // Explicit silence: reports nothing.
        })
        ->build()
    ;

    $silentResult = $silentRunner->run($definition('health.silent'), static fn (): string => 'business-value');
    $silentLogContent = (string) @file_get_contents($silentLog);
} finally {
    ini_restore('error_log');
    @unlink($defaultLog);
    @unlink($silentLog);
}

echo 'default result: ' . $result . PHP_EOL;
echo 'custom result: ' . $customResult . PHP_EOL;
echo 'silent result: ' . $silentResult . PHP_EOL;
echo 'custom handler received failure: ' . ($captured instanceof Throwable ? 'yes' : 'no') . PHP_EOL;
echo 'default sink logged diagnostic: ' . (str_contains($defaultLogContent, '[runit] publication health') ? 'yes' : 'no') . PHP_EOL;
echo 'default sink excludes artifact ids: ' . (!str_contains($defaultLogContent, 'artifact=') ? 'yes' : 'no') . PHP_EOL;
echo 'explicit silence logged nothing: ' . ($silentLogContent === '' ? 'yes' : 'no') . PHP_EOL;
