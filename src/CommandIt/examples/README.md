# CommandIt examples

Runnable, narrated scripts that walk the two axes of the library. Each file is
self-contained: run it directly with `php examples/NN-name.php` after
`composer install`. No framework, server, or database required.

## The two axes

CommandIt separates **what a command is** from **what wraps every command**.

- **Vertical (the command lifecycle).** One command, top to bottom:
  define → register → dispatch → handle → immutable result → lifecycle record
  (`received → executing → succeeded|failed`). This is the axis a feature team
  cares about most.
- **Horizontal (cross-cutting concerns).** Capabilities that apply to *every*
  command regardless of domain: middleware & context enrichment, secret
  redaction, pluggable ports (clock, ids, storage, serializer), and the CLI
  adapter. This is the axis a platform team cares about most.

Most real systems need both. The examples are grouped so you can read one axis
end-to-end, then the other.

## How to run

```bash
composer install
php examples/01-quickstart.php
php examples/02-lifecycle-and-storage.php
php examples/03-middleware-and-context.php
php examples/04-secrets-and-redaction.php
php examples/05-custom-ports.php
php examples/06-cli-adapter.php   # needs symfony/console (a "suggest" dependency)
```

`examples/_bootstrap.php` is shared plumbing (autoload + small `println` /
`section` / `pp` / `findOccurrence` helpers). It is not an example by itself.

## The examples

| File | Axis | Shows |
|------|------|-------|
| `01-quickstart.php` | Vertical | The happy path: define a `signup` command, register a handler, dispatch, read the result and its execution record. |
| `02-lifecycle-and-storage.php` | Vertical | The full state machine including a thrown handler exception (preserved, not replaced), plus retrieving the stored execution by id. |
| `03-middleware-and-context.php` | Horizontal | Two middlewares: one attaches classified context (`trace_id`, `tenant_id`), another times the handler. The context flows into emitted occurrences. |
| `04-secrets-and-redaction.php` | Horizontal | A `Secret`-classified credential attached to context is redacted (`***redacted***`) before any reporter sees it — automatically. |
| `05-custom-ports.php` | Horizontal | Swapping the clock, id generator, serializer, and storage for deterministic, inspectable ports — without touching the command or handler. |
| `06-cli-adapter.php` | Both | The same `signup` command projected onto Symfony Console; `--help`, validation, and exit codes come for free. |

## Where to go next

- `docs/USER_GUIDE.md` — the task-oriented guide behind these snippets.
- `docs/architecture/MODEL.md` — the domain model in depth.
- `docs/adr/` — the load-bearing decisions (RunIt-via-ports, lazy handlers,
  fiber-bound context).
