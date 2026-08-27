# Tutorial 6 — Nesting, flow & errors

**Goal:** compose executions hierarchically, and see how RunIt handles failures while
preserving your original exception.

## Linking executions

An execution can name a **parent** execution. Ownership is expressed by identity, not
by holding collections in memory:

```php
use ItPack\RunIt\Domain\Execution\ExecutionId;

$child = ExecutionDefinition::create(
    ExecutionName::fromString('order.notify'),
    ExecutionKind::fromString('operation'),
    ExecutionId::fromString('exec-parent'),  // parent execution id
);

$runtime->run($child, static function (): void {
    echo "notifying\n";
});
```

## Errors preserve the original exception

When your callable throws, the runner:

1. Records the failure (capturing only a safe, bounded description of the `Throwable`).
2. Resolves any open activities per policy.
3. Completes the execution as failed.
4. Restores the previous context in `finally`.
5. **Rethrows the exact original exception.**

A publication failure never replaces your business exception (ADR-008).

```php
$publisher = new InMemoryArtifactPublisher();

$runtime = RunItBuilder::create()
    ->withPublisher($publisher)
    /* ... same ports as Part 1 ... */
    ->build();

try {
    $runtime->run(
        ExecutionDefinition::create(
            ExecutionName::fromString('order.place'),
            ExecutionKind::fromString('operation'),
        ),
        static function (): void {
            throw new RuntimeException('payment gateway unavailable');
        },
    );
} catch (Throwable $e) {
    echo 'original exception preserved: ' . $e->getMessage() . PHP_EOL;
}

foreach ($publisher->published() as $artifact) {
    if ($artifact instanceof \ItPack\RunIt\Domain\Execution\Execution) {
        echo 'recorded execution status: ' . $artifact->normalize()['status'] . PHP_EOL;
    }
}
```

> **Run this:** [`examples/failed-execution.php`](https://github.com/it-pack/run-it/blob/main/examples/failed-execution.php).

## Why this matters

- Your calling code sees the same exception type and message it would without RunIt.
- The execution is still recorded as `failed`, so observability is complete.
- No global mutable state is left behind — context is restored.

## What you learned

- Executions link via `parentExecutionId`; ownership is by identity.
- On failure, RunIt records the terminal state and rethrows *your* exception.

## Next

[Part 7 — Publish & integrate](07-publish-and-integrate.md): get artifacts out to
OpenTelemetry, CloudEvents, PSR, and beyond.
