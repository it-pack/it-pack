# Tutorial 14 — Buffered publication, overflow & health reporting

**Goal:** bound in-flight artifacts with `BufferedArtifactPublisher`, choose what to
drop when the buffer fills, and observe loss through the internal health reporter
without it ever re-entering the pipeline.

## Buffered publication

`BufferedArtifactPublisher` sits between your code and the final sink. It holds
artifacts in a bounded buffer and flushes them in batches, so a slow exporter never
blocks execution and a burst of traffic is shaped rather than dropped outright.

The buffer is bounded by item count and estimated bytes (not actual process memory):

```php
use ItPack\RunIt\Adapter\Common\DefaultArtifactByteEstimator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\OverflowStrategy;

$sink = new InMemoryArtifactPublisher();
$buffer = new BufferedArtifactPublisher(
    new BufferConfiguration(
        maxItems: 1_024,
        maxEstimatedBytes: 8 * 1024 * 1024,
        maxAgeSeconds: 60,
        batchSize: 256,
        flushIntervalSeconds: 0,
        shutdownFlushBudget: 4096,
        overflow: OverflowStrategy::DropOldest,
    ),
    $sink,
    new SystemMonotonicClock(),
    new DefaultArtifactByteEstimator(),
);

$buffer->publish($observation);
$buffer->flush();      // emits up to batchSize non-expired artifacts
$buffer->close();      // flushes within shutdownFlushBudget, drops + reports the rest
```

`flush()` and `close()` are part of `FlushablePublisherInterface` /
`CloseablePublisherInterface`, so the same buffered instance can be dropped into the
SDK via `withPublisher()`.

## Overflow strategies

When admission would exceed `maxItems` or `maxEstimatedBytes`, the configured
`OverflowStrategy` decides what to drop:

| Strategy | Behavior |
|---|---|
| `DropNewest` | Discards the incoming artifact |
| `DropOldest` | Discards the oldest buffered artifact to make room |
| `DropLowPriority` | Drops low-priority artifacts first (errors and terminal snapshots win) |
| `BlockWithTimeout` | Waits briefly for space, then drops newest |
| `Fail` | Throws `BufferOverflowException` for the failure policy to decide |

```php
$buffer = new BufferedArtifactPublisher(
    new BufferConfiguration(
        maxItems: 8,
        maxEstimatedBytes: 10_000,
        maxAgeSeconds: 60,
        batchSize: 4,
        flushIntervalSeconds: 0,
        shutdownFlushBudget: 1024,
        overflow: OverflowStrategy::DropLowPriority,
    ),
    $sink,
    new SystemMonotonicClock(),
    new DefaultArtifactByteEstimator(),
);
```

## Health reporting

Dropped and expired artifacts are reported to the internal health reporter, so loss
stays observable. The reporter never calls back into the guarded pipeline, so a
faulty reporter cannot loop:

```php
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use Throwable;

final class LoggingReporter implements InternalHealthReporterInterface
{
    public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
    {
        // Record the drop without exception messages or artifact identifiers
        // leaking into the business path.
        error_log($error->getMessage());
    }
}

$buffer = new BufferedArtifactPublisher(
    $config,
    $sink,
    new SystemMonotonicClock(),
    new DefaultArtifactByteEstimator(),
    new LoggingReporter(), // dropped artifacts land here
);
```

When a reporter throws while reporting a drop, RunIt falls back to fixed,
payload-free diagnostics — no identifiers or messages escape.

## Failure policy on overflow

`PublicationPipeline` wraps the buffered publisher in `FailSafeArtifactPublisher`.
When you supply no `failurePolicy`, the default is `FailOpenPolicy`: a buffer
overflow is contained and the business result is preserved. `FailClosedPolicy`
rethrows instead. See
[Tutorial 13 — Publisher failure policies & resilience](13-publisher-failure-policies-and-resilience.md).

> **Run this:** `examples/buffered-publication.php`

## What you learned

- `BufferedArtifactPublisher` bounds the artifact buffer by item count and
  estimated bytes, then flushes in bounded batches.
- Five overflow strategies control what to drop when the buffer is full.
- `flush()` emits up to `batchSize` artifacts; `close()` flushes within the
  shutdown budget and drops/reports the remainder.
- Drops are reported to the internal health reporter, which is payload-free and
  cannot recurse into the pipeline.
- The pipeline defaults to fail-open on overflow; fail-closed rethrows.

## Next

Browse the [Recipes](../README.md#recipes) for your exact stack, or read
[architecture/publication.md](../architecture/publication.md) for the full
pipeline specification.
