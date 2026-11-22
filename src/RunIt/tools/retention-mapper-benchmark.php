<?php

declare(strict_types=1);

// Fresh process per sample; historical mode loads the pre-change store from a file.
require dirname(__DIR__) . '/vendor/autoload.php';

use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfigBuilder;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanStoreInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\InMemoryExecutionSpanStore;
use ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\SDK\Trace\ReadableSpanInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\NoopSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;

final class UnboundedBenchmarkStore implements ExecutionSpanStoreInterface
{
    private array $contexts = [];

    public function recordExecution(string $executionId, SpanContextInterface $context): void
    {
        $this->contexts['e:' . $executionId] = $context;
    }

    public function recordActivity(string $activityId, SpanContextInterface $context): void
    {
        $this->contexts['a:' . $activityId] = $context;
    }

    public function executionContext(string $executionId): ?SpanContextInterface
    {
        return $this->contexts['e:' . $executionId] ?? null;
    }

    public function activityContext(string $activityId): ?SpanContextInterface
    {
        return $this->contexts['a:' . $activityId] ?? null;
    }
}

final class BenchmarkSpanProcessor extends NoopSpanProcessor
{
    public ?SpanContextInterface $context = null;
    public ?SpanContextInterface $parent = null;
    public int $ended = 0;

    public function onEnd(ReadableSpanInterface $span): void
    {
        ++$this->ended;
        $this->context = $span->getContext();
        $this->parent = $span->getParentContext();
    }
}

$mode = $argv[1] ?? '';
$executions = filter_var($argv[2] ?? '10000', FILTER_VALIDATE_INT);
$capacity = filter_var($argv[3] ?? '10000', FILTER_VALIDATE_INT);
if (!in_array($mode, ['bounded', 'unbounded', 'historical'], true) || $executions === false || $executions < 2
    || $capacity === false || $capacity < 1 || $argc > 5
    || ($mode === 'historical' && ($argc !== 5 || !is_file($argv[4])))
    || ($mode !== 'historical' && $argc > 4)) {
    fwrite(STDERR, 'Usage: php tools/retention-mapper-benchmark.php bounded|unbounded|historical '
        . "[executions>=2] [capacity>=1] [historical-store-file]\n");
    exit(1);
}

if ($mode === 'historical') {
    $storeFile = realpath($argv[4]);
    if ($storeFile === false || pathinfo($storeFile, PATHINFO_EXTENSION) !== 'php'
        || (!str_starts_with($storeFile, '/tmp/kilo/')
            && !str_starts_with($storeFile, getcwd() . '/'))) {
        fwrite(STDERR, "Historical store file must be a .php file under /tmp/kilo/ or the working directory\n");
        exit(1);
    }
    require $storeFile;
}

$processor = new BenchmarkSpanProcessor();
$provider = new TracerProvider($processor);
$config = new BridgeConfigBuilder()->withTracerProvider($provider)->build();
$store = match ($mode) {
    'bounded' => new InMemoryExecutionSpanStore($capacity),
    'historical' => new InMemoryExecutionSpanStore(),
    default => new UnboundedBenchmarkStore(),
};
$publisher = TelemetryPublisher::fromConfig($config, $store);

// Warm the same publisher, then use a fresh store so warmup does not affect retention.
$publisher->publish(RunItArtifactFactory::execution('warm', 'work', 'internal', 'succeeded'));
$publisher->publish(RunItArtifactFactory::activity('warm-a', 'warm', 'work', 'internal', 'succeeded'));
$store = match ($mode) {
    'bounded' => new InMemoryExecutionSpanStore($capacity),
    'historical' => new InMemoryExecutionSpanStore(),
    default => new UnboundedBenchmarkStore(),
};
$publisher = TelemetryPublisher::fromConfig($config, $store);
gc_collect_cycles();
$usedBefore = memory_get_usage(false);
$allocatedBefore = memory_get_usage(true);
$started = hrtime(true);
$immediateMatches = 0;
$expected = [];
for ($i = 0; $i < $executions; ++$i) {
    $id = 'e' . $i;
    $activity = 'a' . $i;
    $publisher->publish(RunItArtifactFactory::execution($id, 'work', 'internal', 'succeeded'));
    $executionContext = $processor->context;
    $publisher->publish(RunItArtifactFactory::activity($activity, $id, 'work', 'internal', 'succeeded'));
    $immediateMatches += (int) ($processor->parent->getSpanId() === $executionContext->getSpanId()
        && $processor->parent->getTraceId() === $executionContext->getTraceId());
    if ($i === 0 || $i === $executions - 1) {
        $expected[$i] = [$executionContext, $processor->context];
    }
}
$elapsed = hrtime(true) - $started;
$usedAfter = memory_get_usage(false);
$allocatedAfter = memory_get_usage(true);
$peakUsed = memory_get_peak_usage(false);
$peakAllocated = memory_get_peak_usage(true);
$status = is_readable('/proc/self/status') ? file_get_contents('/proc/self/status') : false;
preg_match('/^VmRSS:\s+(\d+)\s+kB/m', $status ?: '', $rss);
preg_match('/^VmHWM:\s+(\d+)\s+kB/m', $status ?: '', $hwm);

// Probe newest first; each probe itself records an activity and may evict entries.
$probes = [];
foreach ([$executions - 1, 0] as $i) {
    [$executionContext, $activityContext] = $expected[$i];
    $publisher->publish(RunItArtifactFactory::activity(
        'probe-' . $i,
        'e' . $i,
        'probe',
        'internal',
        'succeeded',
        'a' . $i,
    ));
    $parent = $processor->parent;
    $activityMatches = $parent->getSpanId() === $activityContext->getSpanId()
        && $parent->getTraceId() === $activityContext->getTraceId();
    $executionMatches = $parent->getSpanId() === $executionContext->getSpanId()
        && $parent->getTraceId() === $executionContext->getTraceId();
    $probes[$i === 0 ? 'oldest' : 'newest'] = [
        'actual_parent' => $activityMatches
            ? 'activity'
            : (
                $executionMatches
                ? 'execution'
                : (
                    $parent->isValid()
                    ? 'other'
                    : 'root'
                )
            ),
    ];
}
if ($processor->ended !== 2 + 2 * $executions + 2 || $immediateMatches !== $executions) {
    fwrite(STDERR, "Unexpected span count or immediate parent correlation\n");
    exit(2);
}
print json_encode([
    'php' => PHP_VERSION, 'mode' => $mode, 'executions' => $executions, 'capacity' => $capacity,
    'timed_publications' => 2 * $executions, 'elapsed_ns' => $elapsed,
    'ns_per_publication' => $elapsed / (2 * $executions), 'immediate_execution_parent_matches' => $immediateMatches,
    'used_delta_bytes' => $usedAfter - $usedBefore, 'allocated_delta_bytes' => $allocatedAfter - $allocatedBefore,
    'peak_used_bytes' => $peakUsed, 'peak_allocated_bytes' => $peakAllocated,
    'rss_kib' => isset($rss[1]) ? (int) $rss[1] : null, 'peak_rss_kib' => isset($hwm[1]) ? (int) $hwm[1] : null,
    'delayed_parent_probes' => $probes,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
