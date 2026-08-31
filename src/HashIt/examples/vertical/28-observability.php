<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Adapter\Observability\CloudEventsHashingObserver;
use ItPack\HashIt\Adapter\Observability\OtelHashingObserver;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashObservation;
use ItPack\HashIt\HashingObserverInterface;

banner('Observability — plug a listener into every hashing operation');

// 1) A custom observer (always available): print a compact log line per event.
final class PrintingObserver implements HashingObserverInterface
{
    public function observe(HashObservation $o): void
    {
        $op = $o->operation?->name ?? '?';
        $id = substr($o->id, 0, 6);

        echo match ($o->kind) {
            HashObservation::STARTED => "  [start #$id] $op algo={$o->algorithm} bytes={$o->bytesTotal}\n",
            HashObservation::PROGRESS => "  [progress #$id] {$o->bytesProcessed}/{$o->bytesTotal}\n",
            HashObservation::COMPLETED => "  [done #$id] {$o->bytes} bytes in {$o->durationMs} ms -> {$o->resultUrn}\n",
            HashObservation::FAILED => "  [fail #$id] " . ($o->error?->getMessage() ?? '') . "\n",
            default => '',
        };
    }
}

$hasher = NativeHashIt::create(observer: new PrintingObserver());
echo "Custom observer watching a stream hash:\n";
$stream = fopen('php://temp', 'r+b');
fwrite($stream, str_repeat('z', 300_000));
rewind($stream);
$hasher->hashStream($stream, HashAlgorithm::SHA256, 64 * 1024);
fclose($stream);

// 2) OpenTelemetry adapter (optional dependency: open-telemetry/api).
if (class_exists(OtelHashingObserver::class)) {
    try {
        $otel = new OtelHashingObserver();
        NativeHashIt::create(observer: $otel)->hash('observed by otel');
        echo "\nOpenTelemetry adapter: spans emitted (no SDK configured => no-op spans, no error)\n";
    } catch (\Throwable $e) {
        echo "\nOpenTelemetry adapter skipped: " . $e->getMessage() . "\n";
    }
} else {
    echo "\nOpenTelemetry adapter skipped (install open-telemetry/api to enable)\n";
}

// 3) CloudEvents adapter (optional dependency: cloudevents/sdk-php).
if (class_exists(CloudEventsHashingObserver::class)) {
    try {
        $ce = new CloudEventsHashingObserver();
        NativeHashIt::create(observer: $ce)->hash('observed by cloudevents');
        echo 'CloudEvents emitted: ' . count($ce->collected()) . " event(s)\n";
        foreach ($ce->collected() as $event) {
            echo '  type=' . $event->getType() . ' id=' . $event->getId() . "\n";
        }
    } catch (\Throwable $e) {
        echo "\nCloudEvents adapter skipped: " . $e->getMessage() . "\n";
    }
} else {
    echo "\nCloudEvents adapter skipped (install cloudevents/sdk-php to enable)\n";
}
