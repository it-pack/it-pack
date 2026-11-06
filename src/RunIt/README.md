# RunIt

> **The execution model and runtime backbone for the ItPack ecosystem.**

[![PHP](https://img.shields.io/badge/PHP-%3E%3D%208.4-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-release%20candidate-blue.svg)](#project-status)

RunIt provides composable, reusable, and non-intrusive execution components for PHP applications and libraries.

It models **execution itself** rather than coupling application code directly to tracing, logging, metrics, event, or runtime technologies.

```text
Execution
├── Context
├── Activity
├── Measurement
├── Observation
├── Occurrence
└── Snapshot
```

OpenTelemetry, CloudEvents, PSR interfaces, Fibers, and Swoole are integrations around this model—not the model itself.

> **Everything happens within an execution.**

The canonical semantic resources are the
[RunIt OWL vocabulary](vocab/run-it.ttl) and its companion
[SKOS taxonomy](vocab/run-it-taxonomy.ttl). Public contracts and documentation
refer to the relevant terms as they are implemented.

---

## Table of Contents

- [Purpose](#purpose)
- [Project Status](#project-status)
- [Requirements](#requirements)
- [Installation](#installation)
- [Core Concepts](#core-concepts)
- [Quick Start](#quick-start)
- [Architecture](#architecture)
- [Integrations](#integrations)
- [Documentation](#documentation)
- [Security and Privacy](#security-and-privacy)
- [Performance](#performance)
- [Testing](#testing)
- [Development](#development)
- [Roadmap](#roadmap)
- [Versioning](#versioning)
- [Contributing](#contributing)
- [License](#license)

---

## Purpose

Runtime behavior is commonly represented through unrelated systems:

- Traces and spans.
- Logs.
- Metrics.
- Events.
- Configuration snapshots.
- Queue metadata.
- Request context.
- Diagnostic reports.

These systems frequently describe the same operation using incompatible vocabularies.

RunIt establishes a shared semantic foundation:

```text
Execution

    owns Context

    contains Activities

    records Measurements

    captures Observations

    publishes Occurrences

    produces Snapshots
```

This allows ItPack packages, applications, and third-party libraries to cooperate without depending on a specific telemetry vendor, framework, event bus, logger, or execution runtime.

### Example execution kinds

RunIt can represent:

- HTTP requests.
- CLI commands.
- Queue jobs.
- Configuration resolution.
- Workflows.
- Event processing.
- Scheduled tasks.
- Background workers.
- Build processes.
- Database migrations.
- LLM pipelines.
- Custom operations.

---

## Project Status

RunIt is currently at **release candidate `1.0.0-rc.1`**.

The public API is **frozen**. It comprises 372 public symbols (147 interfaces, 206 classes, 13 enums) plus a stable normalization schema, recorded in `tools/api-compat/api-baseline.json` and `tools/api-compat/normalization-schema-baseline.json`. Backward compatibility is enforced by `composer api:check`.

Through the release candidate, a change to a frozen public symbol is a deliberate, documented act: update the baseline with `composer api:baseline` and record the change in `docs/CHANGELOG.md` (see [`docs/releases/1.0.0-rc.1.md`](docs/releases/1.0.0-rc.1.md)).

The initial development priorities are complete:

1. Stable execution semantics.
2. Immutable domain artifacts.
3. Lifecycle-safe execution and activity runners.
4. Context propagation.
5. Composable artifact publication.
6. OpenTelemetry integration.
7. CloudEvents integration.
8. PSR integration.
9. Fiber and Swoole runtime adapters.
10. Adoption by other ItPack packages and a third-party usability sample (`it-pack/run-it-third-party-sample`).

Remaining work toward `1.0.0` and beyond is tracked in [`docs/ROADMAP.md`](docs/ROADMAP.md).

---

## Requirements

- PHP 8.4 or later.
- Composer 2.
- No framework requirement.
- No mandatory PHP extension beyond those required by PHP itself.

Optional integrations may require additional packages or extensions:

- OpenTelemetry PHP API and SDK.
- CloudEvents PHP SDK.
- PSR-3 logger interfaces.
- PSR-14 event dispatcher interfaces.
- PSR-15 HTTP server interfaces.
- PSR-20 clock interfaces.
- Swoole or OpenSwoole.
- FFI for selected Fiber-aware OpenTelemetry configurations.

---

## Installation

Install RunIt through Composer:

```bash
composer require it-pack/run-it
```

For the release candidate, pin to the RC constraint:

```bash
composer require it-pack/run-it:^1.0.0@RC
```

The stable `^1.0.0` constraint becomes available once `1.0.0` is tagged.

RunIt is designed to remain usable without installing telemetry exporters, framework bridges, or runtime extensions.

---

## Core Concepts

RunIt describes one operation as an **execution** that carries immutable **context**
and emits six kinds of artifact. Each is documented in depth under
[`docs/concepts/`](docs/concepts/semantic-model.md); a hands-on tour is the
[**tutorial**](docs/tutorial/01-hello-execution.md).

| Concept | One-line definition | Reference |
|---|---|---|
| **Execution** | One logical operation; the root aggregate everything belongs to. | [concepts/execution.md](docs/concepts/execution.md) · [tutorial 1](docs/tutorial/01-hello-execution.md) |
| **Activity** | A bounded unit of work (a step) inside an execution; may nest. | [concepts/activity.md](docs/concepts/activity.md) · [tutorial 2](docs/tutorial/02-activities.md) |
| **Measurement** | A quantitative, named, typed, unit-bearing value. | [concepts/measurement.md](docs/concepts/measurement.md) · [tutorial 3](docs/tutorial/03-measure-and-observe.md) |
| **Observation** | A qualitative note at a PSR-3 severity; throwables are safely normalized. | [concepts/observation.md](docs/concepts/observation.md) · [tutorial 3](docs/tutorial/03-measure-and-observe.md) |
| **Occurrence** | An immutable, versioned fact (`order.placed.v1`); maps to CloudEvents. | [concepts/occurrence.md](docs/concepts/occurrence.md) · [tutorial 4](docs/tutorial/04-occurrences-and-snapshots.md) |
| **Snapshot** | An immutable state projection bound to a content hash. | [concepts/snapshot.md](docs/concepts/snapshot.md) · [tutorial 4](docs/tutorial/04-occurrences-and-snapshots.md) |
| **Context** | Immutable, classified per-execution metadata; secrets are deny-by-default. | [concepts/context.md](docs/concepts/context.md) · [tutorial 5](docs/tutorial/05-context-and-propagation.md) |

New here? Start with [**Getting Started**](docs/getting-started.md).

---

## Quick Start

The canonical API executes a callable within an explicit execution scope:

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Application\Runner\ExecutionRunnerInterface;
use ItPack\RunIt\Application\Scope\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;

/** @var ExecutionRunnerInterface $runner */
$result = $runner->run(
    ExecutionDefinition::named('configuration.resolve')
        ->ofKind('configuration.resolution'),
    static function (ExecutionScopeInterface $execution): array {
        return [
            'environment' => 'production',
            'debug' => false,
        ];
    },
);
```

The runner is responsible for:

1. Creating the execution.
2. Attaching its context.
3. Publishing lifecycle artifacts.
4. Invoking the operation.
5. Recording success or failure.
6. Producing the terminal execution snapshot.
7. Restoring the previous execution context.
8. Returning the result or rethrowing the original exception.

See the full, runnable version in [`docs/getting-started.md`](docs/getting-started.md)
and [`examples/sdk.php`](examples/sdk.php).

---

## Architecture

RunIt uses hexagonal (ports-and-adapters) architecture. The immutable domain model is
the center; application services orchestrate it; the SDK and external adapters sit at
the edge; and every technology-specific effect crosses the boundary through an explicit
**port**.

```text
SDK / Consumer Adapters → Application → Domain
Infrastructure Adapters ──► Ports ◄── Application
```

The domain never depends on application services, the SDK, or any adapter; external
adapters are optional and never referenced by the domain (ADR-006, ADR-010).

- Dependency rules and the generated layer graph: [`docs/architecture/dependency-graph.md`](docs/architecture/dependency-graph.md).
    - Lifecycle, publication, context propagation, security, performance, self-instrumentation: [`docs/architecture/`](docs/architecture/hexagonal-architecture.md).
    - Runtime architecture diagram (components, primary request path, ports/adapters, trust boundary): [`docs/diagram/runit-runtime.html`](docs/diagram/runit-runtime.html).

---

## Integrations

RunIt's adapters are optional and sit at the edge. Pick what you need:

- **OpenTelemetry** — executions→traces, activities→spans, measurements→metrics. See [`docs/integrations/opentelemetry.md`](docs/integrations/opentelemetry.md).
- **CloudEvents** — occurrences as versioned events, both directions. See [`docs/integrations/cloudevents.md`](docs/integrations/cloudevents.md).
- **PSR-3 / PSR-14** — observations and occurrences to logs and events. See [`docs/integrations/psr.md`](docs/integrations/psr.md).
- **HTTP** — per-request execution context and middleware. See [`docs/integrations/README.md`](docs/integrations/README.md).
- **Swoole / OpenSwoole** — coroutine-local context and server middleware. See [`docs/integrations/swoole.md`](docs/integrations/swoole.md).
- **Fibers** — per-Fiber context isolation. See [`docs/integrations/fibers.md`](docs/integrations/fibers.md).

---

## Documentation

The documentation has a front door: [**`docs/index.md`**](docs/index.md).

- **Evaluating RunIt?** Read [Purpose](#purpose), the [Execution Model](docs/EXECUTION.md), [Use cases](docs/use-cases.md), and the [Ecosystem](docs/ecosystem.md).
- **Integrating it?** Start at [Getting Started](docs/getting-started.md), follow the [tutorial](docs/tutorial/01-hello-execution.md), then pick a [recipe](docs/index.md#full-map) and an [integration](docs/integrations/README.md).
- **Extending it?** Read the [Architecture](docs/architecture/hexagonal-architecture.md), [Base contracts](docs/concepts/base-contracts.md), the [ADR index](docs/adr/README.md), and the [Third-party adapter recipe](docs/recipes/third-party-adapter.md).

Other entry points: [glossary](docs/glossary.md), [vocabulary](docs/vocabulary.md)
(the canonical OWL ontology + SKOS taxonomy), [compatibility](docs/compatibility.md),
[conformance](docs/conformance.md), and the [roadmap](docs/ROADMAP.md).

---

## Security and Privacy

Context, observations, occurrences, and snapshots are treated as potential data
exfiltration paths. Secret values are never exported or serialized; restricted values
are masked on redaction; cardinality and payload sizes are bounded by default. These are
deny-by-default behaviors, with negative tests required for security-sensitive changes.

- Threat model and supply chain: [`docs/SECURITY.md`](docs/SECURITY.md), [`docs/security/`](docs/security/threat-model.md).
- Classification and redaction behavior: [`docs/architecture/security.md`](docs/architecture/security.md).

## Performance

RunIt is built for request, command, job, worker, and library hot paths. The core avoids
network/filesystem access, reflection in ordinary execution, eager stack-trace
collection, and global mutable state. Durations use monotonic time.

Tuning levers include `NullArtifactPublisher`, sampling, deferred normalization, bounded
buffering, batch exporters, and activity filtering. Details:
[`docs/architecture/performance.md`](docs/architecture/performance.md).

## Testing

RunIt uses a test-framework-agnostic strategy: domain invariants, lifecycle
state-machine, application service, adapter contract, integration, property-based,
failure-injection, Fiber/coroutine isolation, leak, serialization round-trip, mutation,
and benchmark tests. Reusable contract suites let third-party adapters verify
compatibility without adopting RunIt's internal framework. See [`docs/QA.md`](docs/QA.md).

---

## Development

Clone the repository and install dependencies:

```bash
git clone <repository>
cd run-it
composer install
```

### Quality checks

The package provides these quality commands:

```bash
composer validate --strict
composer refactor:dry
composer cs:check
composer test
composer analyse
composer architecture
composer api:check
composer mutation
composer benchmark
composer audit
composer examples
composer quality
```

Run `composer refactor` to apply Rector changes before running `composer cs:fix`;
Rector can alter formatting. Review automated changes for behavior and PHP 8.4
compatibility: the current Rector configuration enables PHP 8.5 sets. Regenerate
API baselines only with explicit approval; see `AGENTS.md` and `docs/compatibility.md`.

The aggregate check:

```bash
composer quality
```

is check-only and runs, in order:

1. Composer validation.
2. Rector dry-run.
3. Coding-standard checks.
4. PHPStan.
5. phparkitect.
6. Line-length check.
7. Unit, integration, conformance and concurrency tests.
8. Security audit.

Coverage, API compatibility checks, mutation tests and benchmarks are separate.

### Static analysis

RunIt targets the highest practical PHPStan level with:

- Strict rules.
- No first-party baseline.
- No implicit `mixed`.
- Generic type validation.
- Exception declaration checks.
- Custom architectural rules.

### Dependency architecture

DepTrac protects the intended dependency direction:

```text
Domain       → Contracts
Application  → Contracts, Domain, Ports
SDK          → Contracts, Domain, Application, Ports
Adapters     → Contracts, Domain, Ports, external APIs
```

The domain must never depend on application services or adapters.

### Mutation testing

Mutation testing focuses on:

- Lifecycle transitions.
- Context restoration.
- Failure policies.
- Redaction.
- Sampling.
- Identifier validation.
- Adapter mappings.
- Exception preservation.

### Coding standards

All PHP files must:

- Declare strict types.
- Follow the project coding standard.
- Use explicit parameter and return types.
- Avoid unnecessary inheritance.
- Avoid mutable static state.
- Keep concrete classes `final`.
- Use `readonly` where practical.

---

## Roadmap

RunIt `1.0.0-rc.1` implements the complete initial feature set described in this document:

- The domain kernel (execution, context, activity, measurement, observation, occurrence, snapshot) with deterministic normalization.
- Lifecycle-safe execution and activity runners, explicit and ambient context propagation, and nested-execution policies.
- A composable artifact-publication pipeline with redaction, sampling, buffering, backpressure, failure policies, and recursion protection.
- An SDK with immutable builders, callable and interface decorators, and metadata attributes.
- Integrations for OpenTelemetry, CloudEvents, PSR-3, PSR-14, PSR-20, PSR-7/15/18, Fibers, and Swoole/OpenSwoole.
- Third-party usability, validated by the `it-pack/run-it-third-party-sample` package.

The public API is frozen at this release candidate. Remaining milestones, including the promotion to `1.0.0` and post-1.0 planning, are tracked in [`docs/ROADMAP.md`](docs/ROADMAP.md). The authoritative compatibility baseline is `tools/api-compat/api-baseline.json`.

---

## Versioning

RunIt follows Semantic Versioning after `1.0.0`.

Public API includes:

- Public interfaces.
- Public classes.
- Public enums.
- Public attributes.
- Public method signatures.
- Normalized artifact field names.
- Occurrence naming rules.
- Serialized schema formats explicitly marked stable.

The `ItPack\RunIt\Behavior` and `ItPack\RunIt\Conformance` namespaces are internal and are not covered by the backward-compatibility guarantee. As of `1.0.0-rc.1`, the remaining public namespaces (`Domain`, `Application`, `Contract`, `Adapter`, `SDK`, `Common`) are frozen.

Before `1.0.0`:

- Minor versions may contain breaking changes.
- Breaking changes must be documented.
- Migration notes should accompany each pre-release.
- Deprecated APIs should be retained where practical until the next minor release.

From `1.0.0-rc.1` onward, any change to a frozen public symbol is a breaking change and requires a deliberate, documented baseline update (`composer api:baseline` + `docs/CHANGELOG.md`).

---

## Contributing

Contributions are welcome. The public API is frozen at `1.0.0-rc.1`, so propose any API change through an issue and an ADR before implementation.

Before submitting a change:

1. Open or reference an issue.
2. Explain the execution-model use case.
3. Preserve dependency direction.
4. Add or update tests.
5. Add contract tests for new extension points.
6. Update documentation.
7. Run the complete quality pipeline.
8. Document backward-compatibility impact.
9. Include an ADR for significant architectural decisions.

New features should be built from small, focused components rather than by expanding existing interfaces with unrelated responsibilities.

---

## Design Philosophy

RunIt is intentionally not defined in terms of an external standard.

```text
OpenTelemetry is an adapter.

CloudEvents is an adapter.

PSR-3 is an adapter.

PSR-14 is an adapter.

Swoole is an adapter.
```

The semantic model remains:

> **Execution carries context.**
> **Activities perform work.**
> **Measurements quantify work.**
> **Observations describe work.**
> **Occurrences record work.**
> **Snapshots preserve work.**

---

## License

RunIt is released under the [MIT License](LICENSE).
