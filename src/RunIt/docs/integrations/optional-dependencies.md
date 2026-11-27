# Optional dependencies

RunIt's core has a deliberately tiny runtime footprint. The execution model, the
application runtime, the ports, and the dependency-free first-party adapters
(Null, Memory, Callback, Fiber, Common) require **no** vendor integration packages.
Use of adapters is opt-in, but the RunIt package directly requires the CloudEvents
SDK and OpenTelemetry semantic conventions even when those adapters are not wired.

This page is the consolidated view. Each integration guide repeats the
integration-specific packages under its own "Required optional packages" heading.

## What the core always requires

```json
"require": {
    "php": "^8.4",
    "ext-mbstring": "*",
    "cloudevents/sdk-php": "^1.2",
    "just-bases/php-obs-base": "^1.0",
    "open-telemetry/sem-conv": "^1.45"
}
```

`php-obs-base` is the shared ItPack base package. Consult `composer.json` for
the authoritative direct dependency constraints and Composer for transitives.

## Suggested (opt-in) packages

These are declared under `suggest` in `composer.json`. Installing one enables the
matching adapter; omitting it leaves RunIt fully usable.

| Package | Enables | Integration |
|---|---|---|
| `psr/clock` | PSR-20 clock (`Psr20Clock`) | PSR |
| `psr/log` | PSR-3 observation export and logger decorators | PSR |
| `psr/event-dispatcher` | PSR-14 occurrence dispatch | PSR |
| `psr/http-message` | HTTP context extraction / enrichment | PSR |
| `psr/http-server-handler` | PSR-15 execution middleware | PSR |
| `open-telemetry/api` | OpenTelemetry mapping (traces, metrics, logs) | OpenTelemetry |
| `open-telemetry/sdk` | OpenTelemetry SDK / real exporters | OpenTelemetry |
| `ext-swoole` | Swoole coroutine context storage and server middleware | Swoole |
| `ext-openswoole` | OpenSwoole coroutine context storage and server middleware | Swoole |

## Dev and example dependencies

The packages below are `require-dev`: they back the test suite, the examples, and
the conformance/integration fixtures. They are **not** needed by a consuming
application.

| Package | Used for |
|---|---|
| `symfony/http-client`, `nyholm/psr7` | PSR-18 HTTP delivery and examples |
| `psr/http-client`, `psr/http-server-middleware` | PSR HTTP adapters in tests/examples |
| `open-telemetry/api`, `open-telemetry/sdk` | OpenTelemetry bridge tests and examples |
| `just-bases/php-unit-test-base`, `just-bases/php-qa-base`, `just-bases/php-obs-dev-base` | testing and QA bases |

Because PHP lazy-loads classes, an adapter only fails if it is actually
instantiated. The rest of the library works whether or not any PSR, OpenTelemetry,
Swoole, or OpenSwoole optional package is present. The CloudEvents SDK and
OpenTelemetry semantic conventions remain package dependencies regardless of use.

## Installation patterns

Install only what you wire:

```bash
# OpenTelemetry, with a real exporter
composer require open-telemetry/api open-telemetry/sdk

# CloudEvents over HTTP (SDK already installed with RunIt)
composer require psr/http-client psr/http-factory
composer require symfony/http-client nyholm/psr7   # a concrete client

# PSR-3 / PSR-14 only
composer require psr/log psr/event-dispatcher

# Swoole or OpenSwoole runtime
composer require ext-swoole      # or ext-openswoole
```

## No global provider configuration

Adapters never configure global providers or select an exporter. The application
owns provider construction and passes it into the bridge through a config object (for
example `BridgeConfigBuilder` for OpenTelemetry). This keeps the core independent of
any vendor bootstrap and lets a consumer enable only the signals it needs.

## Related

- [`opentelemetry.md`](opentelemetry.md) — required optional packages and provider responsibility.
- [`cloudevents.md`](cloudevents.md) — SDK-backed mapping and HTTP delivery packages.
- [`psr.md`](psr.md) — PSR interface packages per adapter.
- [`swoole.md`](swoole.md) — Swoole / OpenSwoole extension versions.
- [`compatibility-matrix.md`](compatibility-matrix.md) — version and capability matrix.
- `AGENTS.md` (External Adapter Rules) and the ADRs ADR-006, ADR-010, ADR-020,
  ADR-021, ADR-022.
