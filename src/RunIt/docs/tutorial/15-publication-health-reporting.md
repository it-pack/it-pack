# Tutorial 15 — Publication health reporting

**Goal:** understand how RunIt reports publication failures when no health handler is
configured, how to supply a custom handler, and how to opt into explicit silence.
Also covers the sanitized default sink (no artifact identifiers) and the
`Critical` diagnostic for open activities at completion.

## Default error-log sink (Phase 4)

An omitted or `null` `onPublicationFailure` handler no longer leaves publisher
failures silent. RunIt reports them to the replaceable SDK error-log default
(`ErrorLogHealthReporter` → PHP `error_log`), including when a runner is built
through `RunItBuilder` without a handler. The original business result or
exception is always preserved.

```php
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

// No handler: publication failures reach the SDK error-log default sink.
$runtime = RunItBuilder::create()
    ->withPublisher(new ThrowingPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$result = $runtime->run(
    ExecutionDefinition::create(
        ExecutionName::fromString('order.place'),
        ExecutionKind::fromString('operation'),
    ),
    static fn (): string => 'business-value',
);

// $result is 'business-value' even though the publisher threw; the failure is
// logged to the error log instead.
```

Diagnostics exclude artifact identifiers so no business identifier reaches the
error log. Throwable descriptions come from the throwable mapper, which only
truncates messages — truncation is not sanitization, so exception messages may
still contain sensitive data unless an independently sanitizing mapper is used.

## Custom handler

Supply a callback to receive publication failures directly:

```php
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$captured = null;
$runtime = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withPublicationFailureHandler(static function (\Throwable $e) use (&$captured): void {
        $captured = $e;
    })
    ->build();
```

If the handler itself throws, RunIt contains the fault and falls back to a
bounded, payload-free error-log notice instead of replacing the business outcome
(Phase 2 behavior preserved).

## Explicit silence

Opt out of reporting by passing a no-op closure as the handler:

```php
->withPublicationFailureHandler(static function (\Throwable $e): void {
    // Explicit silence: reports nothing.
})
```

This is the only way to disable reporting; `null` or omission means the default
error-log sink.

## Open activities at completion

The default completion policy (`AutoFailOpenActivitiesPolicy`) now records a
`Critical` diagnostic instead of `Warning` when activities are still open at
completion. The policy itself only records the observation — it does not
complete or remove the activity; activity closure is left to the runtime. The
operation-derived terminal status and the exact business result or original
exception are preserved. Use an
explicit completion policy (`withCompletionPolicy(...)`) to alter the terminal
outcome.

```php
use ItPack\RunIt\Application\Policy\FailExecutionOnOpenActivitiesPolicy;

$runtime = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withCompletionPolicy(new FailExecutionOnOpenActivitiesPolicy())
    ->build();
```

> **Run this:** `examples/health-reporting-default.php`

## What you learned

- Default (no handler): publisher failures are reported to the SDK error-log
  default sink; business result/exception preserved.
- Custom handler: receives failures directly; throwing handlers are contained by
  a bounded payload-free fallback.
- Explicit silence: pass a no-op closure as the handler.
- Artifact identifiers are omitted from diagnostics; exception messages are only
  truncated by the throwable mapper and may still contain sensitive data unless
  an independently sanitizing mapper is used.
- Open activities at completion now record a `Critical` diagnostic.

## Next

Read [`architecture/publication.md`](../architecture/publication.md) for the full
publication pipeline specification, or browse the [Recipes](../README.md#recipes)
for your exact stack.
