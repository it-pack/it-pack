# RunIt Architecture Dependency Graph

This diagram is generated from `deptrac.yaml` (DepTrac 4) via:

```bash
vendor/bin/deptrac analyse --no-progress --formatter=mermaidjs
```

`A --> B` means **layer `A` depends on layer `B`**. The graph is the
architecture-enforcement source of truth for the dependency rules below and for
the M13.3 DepTrac completion milestone.

```mermaid
flowchart TD;
    External Adapter -->|141| Contract;
    External Adapter -->|57| Domain;
    External Adapter -->|14| Port;
    Common Adapter -->|39| Contract;
    Common Adapter -->|14| Port;
    Common Adapter -->|6| Domain;
    Application -->|517| Contract;
    Application -->|89| Domain;
    Application -->|106| Port;
    Domain -->|793| Contract;
    Domain -->|61| Common;
    Domain -->|1| Port;
    Test Support -->|38| Contract;
    Test Support -->|29| Domain;
    Test Support -->|19| Port;
    Contract -->|47| Common;
    Port -->|6| Contract;
    SDK -->|31| Port;
    SDK -->|58| Contract;
    SDK -->|10| Application;
    SDK -->|15| Domain;
```

## Layers

| Layer | Source directory | Role |
|---|---|---|
| `Common` | `src/Common` | Shared, dependency-free value-object contracts and primitives. |
| `Contract` | `src/Contract` (excl. `Port`) | Public domain, application, and exception interfaces. |
| `Port` | `src/Contract/Port` | External-effect ports (time, identity, serialization, publication, storage). |
| `Domain` | `src/Domain`, `src/Behavior/Domain` | Immutable execution-model artifacts and validators. |
| `Application` | `src/Application` | Lifecycle runtime, scopes, recorders, policies. |
| `SDK` | `src/SDK` | Programmatic builders, decorators, middleware, attributes. |
| `Common Adapter` | `src/Adapter/{Null,Memory,Callback,Fiber,Common}` | First-party, dependency-free adapters. |
| `External Adapter` | `src/Adapter/{OpenTelemetry,CloudEvents,Psr,Swoole}` | Vendor-integration adapters. |
| `Test Support` | `src/Conformance` | Framework-neutral conformance harness (fixtures, verifiers, results). |

## Enforced dependency rules

- `Common` depends on nothing.
- `Contract` → `Common`.
- `Port` → `Common`, `Contract`.
- `Domain` → `Common`, `Contract`, `Port`.
- `Application` → `Common`, `Contract`, `Port`, `Domain`.
- `SDK` → `Common`, `Contract`, `Port`, `Domain`, `Application`.
- `Common Adapter` → `Common`, `Contract`, `Port`, `Domain`.
- `External Adapter` → `Common`, `Contract`, `Port`, `Domain`.
- `Test Support` → everything (conformance harness only).

## Prohibitions (architecture boundaries)

- **Domain must not depend on Application, SDK, or any adapter.** Enforced by
  omitting those layers from `Domain`'s allowed set.
- **Application must not depend on SDK, Common Adapter, or External Adapter.**
  Enforced by omitting `SDK` and both adapter layers from `Application`'s allowed
  set.
- **External Adapter must not be depended on by Domain or Application.** Enforced
  by the rules above; only `Common Adapter`, other adapters, and `Test Support`
  may reference it.

## Uncovered dependencies (reviewed)

DepTrac reports 134 "uncovered" dependencies. Every one is an in-project class
depending on a class that is **not part of the project** — specifically the
vendor integration points inside `External Adapter` (and a small `Psr` HTTP sink
in `Common Adapter`) referencing `OpenTelemetry\*`, `Psr\*`, `CloudEvents\*`, and
Swoole/OpenSwoole `*`. These are the intended, adapter-confined external effects
required by ADR-006 ("External standards are adapters") and ADR-010 ("Core has no
mandatory external integration"). They are reviewed and accepted; they are not
architecture violations and are not suppressed via skips.

Run `composer architecture` to reproduce the zero-violation report.
