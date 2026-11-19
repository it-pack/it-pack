# Tutorial 13 — Publisher failure policies & resilience

**Goal:** understand how RunIt handles publication failures without replacing your
business exception, and how nested execution policies resolve.

## Publisher failure policies

`FailSafeArtifactPublisher` wraps the inner publisher and applies a failure policy
when it throws. The original business exception and result are always preserved on
SDK paths that enforce it; standalone publisher use may propagate a transport error
unless an enclosing boundary contains it. `FailClosedPolicy::handle()` itself does
not preserve the business result — it decides whether the transport error rethrows.

| Policy | Behavior |
|---|---|
| `FailOpenPolicy` | Swallows the error (default) |
| `FailClosedPolicy` | Rethrows the transport error |
| `CollectAndContinuePolicy` | Collects the error, continues |
| `ReportToFallbackPolicy` | Forwards artifact to fallback publisher |

```php
use ItPack\RunIt\Application\Publication\CollectAndContinuePolicy;
use ItPack\RunIt\Application\Publication\FailClosedPolicy;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Publication\ReportToFallbackPolicy;

// FailOpen: swallows publication error
$publisher = new FailSafeArtifactPublisher($inner, new FailOpenPolicy());

// FailClosed: rethrows transport error (never substitutes business exception)
$publisher = new FailSafeArtifactPublisher($inner, new FailClosedPolicy());

// CollectAndContinue: collects errors for inspection
$publisher = new FailSafeArtifactPublisher(
    $inner,
    new CollectAndContinuePolicy(static function (\Throwable $e, $artifact): void {
        // inspect $e, continue
    }),
);

// ReportToFallback: forward to secondary sink on failure
$publisher = new FailSafeArtifactPublisher(
    $inner,
    new ReportToFallbackPolicy($fallbackPublisher),
);
```

> **Run this:** `examples/failure-policies.php`

## Nested execution policies

`NestedExecutionPolicyInterface` resolves how nested calls behave when an
execution is already active:

- `ChildExecutionPolicy` (default): nested call becomes a child execution linked
  to the parent
- `ActivityOnlyPolicy`: nested call becomes an activity of the parent execution
- `DetachedExecutionPolicy`: nested call becomes a correlated root execution
- `RejectNestedPolicy`: nested call is rejected outright

```php
use ItPack\RunIt\Application\Policy\ChildExecutionPolicy;
use ItPack\RunIt\Application\Policy\ActivityOnlyPolicy;
use ItPack\RunIt\Application\Policy\DetachedExecutionPolicy;
use ItPack\RunIt\Application\Policy\RejectNestedPolicy;

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withNestedExecutionPolicy(new ChildExecutionPolicy())
    ->build()
;
```

> **Run this:** `examples/nested-execution-policy.php`

## What you learned

- Publication failures never replace your business exception — policies decide
  what happens, but the original result/exception flows through unchanged.
- Four failure policies: fail-open, fail-closed, collect-and-continue,
  report-to-fallback.
- Nested execution modes: child_execution, activity_only, detached_execution,
  reject_nested.

## Next

You have completed the tutorial series. Browse the [Recipes](../README.md#recipes)
for your exact stack, or read [architecture/publication.md](../architecture/publication.md)
for the full pipeline specification.
