# Integration compatibility matrix

This matrix records, for each RunIt integration, the package(s) it needs, the
minimum versions, and the capability it provides. All integrations are optional: the
core requires only `php ^8.4` and `just-bases/php-obs-base`. In RunIt's own
`composer.json` the integration packages are listed under `require-dev` (to build and
test the adapters in-tree) and under `suggest` (to advertise them to consumers);
a consuming application adds the ones it wires to its own `require`.

See [`optional-dependencies.md`](optional-dependencies.md) for installation patterns
and [`adapter-failure-policies.md`](adapter-failure-policies.md) for failure
behaviour.

## Runtime

| Requirement | Version | Notes |
|---|---|---|
| PHP | `^8.4` | Core runtime requirement. |
| `just-bases/php-obs-base` | `^1.0` | Shared ItPack base (value-object / QA primitives). Only mandatory runtime dep beyond PHP. |

## Adapters and bridges

| Integration | Required runtime packages | Optional packages | Minimum versions | Capability |
|---|---|---|---|---|
| OpenTelemetry | `open-telemetry/api` | `open-telemetry/sdk` | api `^1.9`, sdk `^1.0` | Map executions/activities → spans, measurements → metrics, observations → logs. SDK-free mapping; SDK only for real export. |
| CloudEvents | none (SDK-free) | `cloudevents/sdk-php`; `psr/http-client` + `psr/http-factory` for HTTP delivery | api n/a; PSR-18 client per app | Map occurrences ↔ CloudEvents 1.0 structured JSON; optional PSR-18 delivery (at-most-once). |
| PSR-3 | `psr/log` | — | `^3.0` | Observation → log record; execution-aware logger decorator; log → observation bridge. |
| PSR-14 | `psr/event-dispatcher` | — | `^1.0` | Occurrence → PSR-14 event dispatch; inbound event → occurrence. |
| PSR-20 | `psr/clock` | — | `^1.0` | `Psr20Clock` implements `MonotonicClockInterface` (wall + monotonic kept separate). |
| PSR-7 | `psr/http-message` | — | (interface) | HTTP context extraction / response correlation enrichment. |
| PSR-15 | `psr/http-server-handler` | `psr/http-server-middleware` | (interface) | `ExecutionMiddleware` wraps each request in one execution. |
| PSR-18 | `psr/http-client` | concrete client (`symfony/http-client`, `nyholm/psr7`) | `^1.0` | Correlation header injection on outbound requests. |
| Fibers | none (core PHP Fibers) | — | PHP 8.1+ (run on 8.4) | `FiberContextStorage`, `FiberExecutionEngine`, per-Fiber scope isolation. |
| Swoole | `ext-swoole` | — | Swoole `>= 4.6` | Coroutine-local context storage, server middleware, worker lifecycle. |
| OpenSwoole | `ext-openswoole` | — | OpenSwoole `>= 4.9` | Same as Swoole via the OpenSwoole extension. |

## Capability summary

| Capability | Integration |
|---|---|
| Trace / span export | OpenTelemetry |
| Metric export | OpenTelemetry |
| Log record export | OpenTelemetry, PSR-3 |
| Portable event envelope | CloudEvents |
| Event dispatch | PSR-14 |
| Fixed/virtual clock | PSR-20 |
| HTTP request scoping | PSR-15, Swoole |
| Outbound correlation propagation | PSR-18 |
| Concurrent scope isolation | Fibers, Swoole / OpenSwoole |

## Constraints and guarantees

- **No global provider configuration.** Adapters consume injected providers; the core
  never selects an exporter or configures a global. OpenTelemetry `null` providers
  disable a signal without error.
- **Cardinality safe by default.** OpenTelemetry drops ids from metric dimensions
  unless an allow-list is set; PSR-15 derives `METHOD /normalized-path` execution
  names so URLs do not become a high-cardinality dimension.
- **Delivery semantics are the transport's.** CloudEvents HTTP delivery is
  at-most-once, best-effort; Swoole imposes no retry/durable guarantee. RunIt makes no
  stronger promise.
- **Runtime selection is exclusive.** `CompositeContextStorage` picks exactly one
  owner per call (coroutine, Fiber, or synchronous stack); it does not nest a Fiber
  inside a coroutine. When neither Swoole nor OpenSwoole is loaded, the Swoole
  detector reports `false` and coroutine storage degrades to main-stack storage.

## Related ADRs

- ADR-006 — External standards are adapters.
- ADR-010 — Core has no mandatory external integration.
- ADR-020 — OpenTelemetry bridge placement.
- ADR-021 — CloudEvents bridge placement.
- ADR-022 — Swoole adapter packaging.
- ADR-013 — Fiber / Swoole adapters.
