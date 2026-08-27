# RunIt Use Cases

This page helps you decide **whether RunIt fits** and **which integration to pick**.
If you have not read the [Getting Started](../getting-started.md) yet, start there.

## What RunIt is (and is not)

RunIt is a **vendor-neutral execution model and runtime backbone**. It gives your code
a uniform way to describe what ran (executions, activities), what happened
(measurements, observations, occurrences, snapshots), and the context it ran in — as
immutable, serializable artifacts.

RunIt is **not** a transport. It does not replace OpenTelemetry, CloudEvents, PSR-3, or
your APM. Those are *adapters* at the edge (ADR-006). RunIt produces the model; the
adapters carry it to whichever systems you already use.

## When RunIt is a good fit

- You want one consistent execution/activity/artifact model across CLI, HTTP, queues,
  workers, and cron — without coupling to a vendor SDK.
- You need immutable, versioned facts (occurrences) and integrity-checked state
  (snapshots) for auditing or event streaming.
- You care about data classification and want secrets denied-by-default before anything
  leaves the process (ADR-012).
- You are building a library or framework and want to emit execution telemetry that
  *any* consumer can adapt (see [ecosystem.md](ecosystem.md)).

## When a lighter approach is enough

- You only need plain logging → use PSR-3 directly.
- You only need tracing → use OpenTelemetry directly.
- You have a single, fixed stack and no need for a portable model.

RunIt earns its place when the model itself is reusable — across services, languages of
deployment, or for third parties.

## Pick your integration

| I need… | Adapter / path | Docs |
|---|---|---|
| Logs and domain events via PSR | PSR-3 / PSR-14 | [integrations/psr.md](../integrations/psr.md) |
| Traces, metrics, logs in OTel | OpenTelemetry bridge | [integrations/opentelemetry.md](../integrations/opentelemetry.md) |
| Event streaming / CloudEvents | CloudEvents bridge | [integrations/cloudevents.md](../integrations/cloudevents.md) |
| Per-request HTTP context | PSR HTTP + middleware | [integrations/README.md](../integrations/README.md) |
| High-concurrency coroutines | Swoole / OpenSwoole | [integrations/swoole.md](../integrations/swoole.md) |
| Per-Fiber / coroutine isolation | Fiber context storage | [integrations/fibers.md](../integrations/fibers.md) |

## Common deployment shapes

| Shape | Start with | Recipe |
|---|---|---|
| CLI command | `run-it` around the command | [recipes/cli-command.md](https://github.com/it-pack/run-it/blob/main/docs/recipes/cli-command.md) |
| HTTP request | Server middleware + OTel/PSR | [recipes/http-request.md](https://github.com/it-pack/run-it/blob/main/docs/recipes/http-request.md) |
| Queue job | Execution per job | [recipes/queue-job.md](https://github.com/it-pack/run-it/blob/main/docs/recipes/queue-job.md) |
| Background worker | Fiber/Swoole isolation | [recipes/background-worker.md](https://github.com/it-pack/run-it/blob/main/docs/recipes/background-worker.md) |
| Scheduler / cron | One execution per tick | [recipes/scheduler.md](https://github.com/it-pack/run-it/blob/main/docs/recipes/scheduler.md) |
| Workflow / pipeline | Nested activities | [recipes/workflow.md](https://github.com/it-pack/run-it/blob/main/docs/recipes/workflow.md) |
| Third-party library | Emit artifacts, no deps | [recipes/third-party-adapter.md](https://github.com/it-pack/run-it/blob/main/docs/recipes/third-party-adapter.md) |

## RunIt vs. a logging / APM library

| Concern | Logging / APM alone | RunIt + adapter |
|---|---|---|
| What ran, as a model | Implicit in spans/logs | Explicit `Execution` root aggregate |
| Structured facts for streaming | Hand-rolled events | Versioned `Occurrence` → CloudEvents |
| State integrity | None | `Snapshot` with content hash |
| Sensitive data | Easy to leak | Deny-by-default secrets (ADR-012) |
| Portability | Vendor-coupled | Vendor-neutral; adapters at the edge |
| Cost | Low | Slightly more structure, far more reuse |

The two are complementary: RunIt produces the model, and your existing OTel/PSR/
CloudEvents stack consumes it.

## Next

- [Ecosystem](ecosystem.md) — packages around RunIt.
- [Tutorial](../tutorial/01-hello-execution.md) — learn the model hands-on.
- [Architecture](../architecture/hexagonal-architecture.md) — why the boundaries exist.
