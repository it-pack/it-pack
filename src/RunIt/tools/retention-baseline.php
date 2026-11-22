<?php

declare(strict_types=1);

// Run each sample in a fresh process: php tools/retention-baseline.php [executions] [activities-per-execution] [duplicate-percent] [id-padding]
require dirname(__DIR__) . '/vendor/autoload.php';

use ItPack\RunIt\Adapter\OpenTelemetry\InMemoryExecutionSpanStore;
use OpenTelemetry\API\Trace\SpanContext;

$executions = (int) ($argv[1] ?? 10000);
$activities = (int) ($argv[2] ?? 4);
$duplicates = (int) ($argv[3] ?? 0);
$padding = (int) ($argv[4] ?? 0);
if ($executions < 1 || $activities < 0 || $duplicates < 0 || $duplicates > 100 || $padding < 0 || $padding > 1024) {
    fwrite(STDERR, "Invalid workload arguments\n");
    exit(1);
}

// Warm autoloading and validation without retaining the warmup store.
$context = SpanContext::create(str_repeat('1', 32), str_repeat('2', 16));
$warmup = new InMemoryExecutionSpanStore();
$warmup->recordExecution('warmup', $context);
$warmup->executionContext('warmup');
unset($warmup, $context);
gc_collect_cycles();
$usedBefore = memory_get_usage(false);
$allocatedBefore = memory_get_usage(true);
$store = new InMemoryExecutionSpanStore();
$suffix = str_repeat('x', $padding);
$start = hrtime(true);
for ($i = 0; $i < $executions; ++$i) {
    $context = SpanContext::create(sprintf('%032x', $i + 1), sprintf('%016x', $i + 1));
    $store->recordExecution('e' . $i . $suffix, $context);
    for ($j = 0; $j < $activities; ++$j) {
        $activityId = 'a' . $i . '-' . $j . $suffix;
        $activityContext = SpanContext::create(
            sprintf('%032x', $i + 1),
            sprintf('%016x', $i * max(1, $activities) + $j + 1),
        );
        $store->recordActivity($activityId, $activityContext);
    }
    if ($i % 100 < $duplicates) {
        $store->recordExecution('e' . $i . $suffix, $context);
        if ($activities > 0) {
            $store->recordActivity($activityId, $activityContext);
        }
    }
}
$writeNs = hrtime(true) - $start;
$usedAfter = memory_get_usage(false);
$allocatedAfter = memory_get_usage(true);
$hits = 0;
$misses = 0;
$start = hrtime(true);
for ($i = 0; $i < $executions; ++$i) {
    $hits += (int) ($store->executionContext('e' . $i . $suffix) !== null);
    if ($activities > 0) {
        $hits += (int) ($store->activityContext('a' . $i . '-0' . $suffix) !== null);
    }
}
$hitNs = hrtime(true) - $start;
$start = hrtime(true);
for ($i = 0; $i < $executions; ++$i) {
    $misses += (int) ($store->executionContext('missing' . $i . $suffix) === null);
}
$missNs = hrtime(true) - $start;
$status = is_readable('/proc/self/status') ? file_get_contents('/proc/self/status') : false;
preg_match('/^VmRSS:\s+(\d+)\s+kB/m', $status ?: '', $rss);
preg_match('/^VmHWM:\s+(\d+)\s+kB/m', $status ?: '', $hwm);
$duplicateExecutions = intdiv($executions, 100) * $duplicates + min($executions % 100, $duplicates);
$writes = $executions * (1 + $activities) + $duplicateExecutions * (1 + (int) ($activities > 0));
print json_encode([
    'executions' => $executions, 'activities_per_execution' => $activities,
    'duplicate_percent' => $duplicates, 'id_padding_bytes' => $padding,
    'distinct_entries' => $executions * (1 + $activities), 'writes' => $writes,
    'used_delta_bytes' => $usedAfter - $usedBefore, 'allocated_delta_bytes' => $allocatedAfter - $allocatedBefore,
    'peak_used_bytes' => memory_get_peak_usage(false), 'peak_allocated_bytes' => memory_get_peak_usage(true),
    'rss_kib' => isset($rss[1]) ? (int) $rss[1] : null, 'peak_rss_kib' => isset($hwm[1]) ? (int) $hwm[1] : null,
    'generation_and_write_ns_per_write' => $writeNs / $writes,
    'delayed_hits' => $hits, 'expected_delayed_hits' => $executions * (1 + (int) ($activities > 0)),
    'delayed_lookup_ns' => $hitNs / ($executions * (1 + (int) ($activities > 0))), 'intentional_misses' => $misses,
    'miss_ns_per_lookup' => $missNs / $executions,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
