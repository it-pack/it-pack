# RunIt Ecosystem

RunIt is intentionally small at the core and grows through **optional adapters** and
**sibling packages**. This page shows the whole picture so you can see what exists and
what is planned.

## Core package — `it-pack/run-it`

This repository. Ships:

- The **domain model** (immutable artifacts) and **application runtime** (lifecycle,
  scopes, recorders).
- The **SDK** (builders, decorators, attributes) for ergonomic adoption.
- **First-party adapters**: `Null`, `Memory`, `Common`, `Fiber`.
- **Optional external adapters**: OpenTelemetry, CloudEvents, PSR, Swoole.
- The **vocabulary** (`vocab/run-it.ttl`, `vocab/run-it-taxonomy.ttl`) that anchors
  domain terms.

The public API is frozen at `1.0.0-rc.1` and protected by a backward-compatibility
baseline. Internal namespaces (`Behavior`, `Conformance`) may change without notice.

## Validation — `it-pack/run-it-third-party-sample`

A sibling package that consumes RunIt **as a third party would**, proving the public
API is usable without being inside the monorepo. If you are building a library on top
of RunIt, mirror its structure. See [docs/THIRD_PARTY_PLAN.md](../THIRD_PARTY_PLAN.md)
and [conformance.md](../conformance.md).

## Reference consumer — ConfigureIt

ConfigureIt is the in-ecosystem consumer that drives RunIt for real configuration
resolution. Its integration is the canonical "how a host application adopts RunIt"
example, described in [`docs/EXECUTION.md`](../EXECUTION.md) ("ConfigureIt Example").
A community integration lives in `it-pack/configure-it-runit`.

## Bridges and future packages

RunIt follows explicit criteria for splitting optional integrations into their own
packages (ADR-016). The roadmap (M16) outlines:

- Standalone **bridge packages** for OpenTelemetry, CloudEvents, and Swoole/OpenSwoole
  once they graduate from first-party adapters (ADR-020, ADR-021, ADR-022).
- Additional adapters as the ecosystem demands (M16.3).
- Operational-maturity tooling around publication and storage (M16.4).
- A careful, additive **API evolution** policy post-1.0 (M16.5).

Until then, the bridges live inside this package as optional, dependency-light adapters
so adoption stays a single `composer require`.

## Vocabulary packages

`vocab/run-it.ttl` (OWL) and `vocab/run-it-taxonomy.ttl` (SKOS) are the canonical term
definitions. Public code references them via PHPDoc `@see`, and public docs link to
them. They are documentation-only — never a runtime dependency (see
[architecture/coding-standards.md](../architecture/coding-standards.md)). Sibling
packages reuse the same foundation: **AsyncIt** (the async/runtime companion package) imports the RunIt
ontology (`owl:imports <https://w3id.org/itpack/runit>`) and reuses terms such as
`runit:Execution`, `runit:Activity`, `runit:duration`, `runit:startedAt`, and
`runit:endedAt` for its task and runtime-adapter model. See
[vocabulary.md](vocabulary.md) for the namespaces and the full term inventory.

## How the pieces fit

```text
Your app / library
      │  emits
      ▼
 RunIt SDK + runtime  (vendor-neutral model)
      │  publishes artifacts through ArtifactPublisherInterface
      ▼
 Optional adapters:  OTel · CloudEvents · PSR · Swoole · Fiber
      │
      ▼
 Your existing systems:  tracing · logs · event bus · HTTP
```

Everything outside the core is **optional and replaceable**. That is the point: RunIt
describes execution; your stack consumes it.

## Next

- [Use cases](use-cases.md) — is RunIt right for your problem?
- [Getting Started](../getting-started.md) — install and run.
- [Releases](releases/1.0.0-rc.1.md) — what shipped in the RC.
