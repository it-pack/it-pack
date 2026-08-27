# Tutorial 5 — Context & classification

**Goal:** attach classified, per-execution **context** and understand how RunIt keeps
sensitive values safe by default.

Context is the metadata that travels with an execution: who initiated it, which tenant,
a correlation id, an API token. RunIt never treats context as free-form — every entry
has a **classification** that decides its default export, propagation, and serialization
behavior.

## Building context

```php
use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ContextKey;
use ItPack\RunIt\Domain\Context\ExecutionContext;

$context = ExecutionContext::empty()
    ->with(ContextEntry::public(ContextKey::fromString('environment'), 'prod'))
    ->with(ContextEntry::internal(ContextKey::fromString('trace.id'), 'abc123'))
    ->with(ContextEntry::restricted(ContextKey::fromString('customer.dob'), '1990-01-01'))
    ->with(ContextEntry::secret(ContextKey::fromString('api.token'), 's3cr3t'));
```

The four classifications:

| Classification | Default edge behavior |
|---|---|
| `public` | Exported and propagated freely. |
| `internal` | Stays within the runtime/process. |
| `restricted` | Masked on redaction; never in unrestricted exports. |
| `secret` | **Never** exported or serialized (deny-by-default). |

> **Run this:** [`examples/context-classification.php`](https://github.com/it-pack/run-it/blob/main/examples/context-classification.php).

## Attaching context to an execution

Pass the context as the fifth argument of `ExecutionDefinition::create()`:

```php
$definition = ExecutionDefinition::create(
    ExecutionName::fromString('order.place'),
    ExecutionKind::fromString('operation'),
    null,        // parent execution id
    [],          // linked execution ids
    $context,    // the classified context
);

$runtime->run($definition, static function (): array {
    return ['status' => 'ok'];
});
```

Inside the callable, read it back via the scope: `$scope->context()`.

## Why deny-by-default?

Classification is **not** an access-control decision — it is a safe default for data
leaving the runtime. Secrets are never exported or serialized; restricted values are
masked during redaction. This protects against accidental exfiltration through logs,
traces, or events (ADR-012). See [architecture/security.md](../architecture/security.md).

## What you learned

- Context entries are classified; classification drives default export behavior.
- Secrets are deny-by-default — never exported or serialized.
- Context is attached through `ExecutionDefinition::create(..., $context)`.

## Next

[Part 6 — Nesting, flow & errors](06-nesting-and-flow.md): how executions compose and
how failures preserve your original exception.
