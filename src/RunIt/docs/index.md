# RunIt Documentation

**RunIt models execution itself.** Every operation produces an *execution* that
carries immutable *context*, is composed of bounded *activities*, and emits
quantitative *measurements*, qualitative *observations*, immutable *occurrences*, and
integrity-checked *snapshots*. It is vendor-neutral, framework-neutral, and ships with
optional adapters for OpenTelemetry, CloudEvents, PSR-3/PSR-14, HTTP, and Swoole.

This page is the **front door**. Pick a path below, or use the full map.

---

## New here?

Start with the **[Getting Started](getting-started.md)** guide: install in one line,
run a 5-minute hello world, and learn what just happened. Then follow the
**[tutorial](tutorial/01-hello-execution.md)** at your own pace.

Prefer to read first? The **[Execution Model](EXECUTION.md)** essay is the conceptual
anchor, and the **[glossary](glossary.md)** defines every term.

---

## Choose your path

### I'm evaluating RunIt (~15 min)
Decide whether it fits before installing.
1. [Purpose & status](../README.md#purpose) in the README.
2. [Execution Model](EXECUTION.md) — the mental model.
3. [Use cases](use-cases.md) — when to use RunIt, and RunIt vs a logging/APM library.
4. [Ecosystem](ecosystem.md) — sibling packages and maturity.

### I'm integrating RunIt into my app (~1–2 h)
Ship a working integration.
1. [Getting Started](getting-started.md) — install + hello world.
2. [Tutorial](tutorial/01-hello-execution.md) — build up the model step by step.
3. [Recipes](../README.md#recipes) — copy the task matching your stack.
4. [Integrations](integrations/README.md) — wire an adapter (PSR, OTel, CloudEvents, …).
5. [Concepts](../README.md#core-concepts) — deep reference on demand.

### I'm building an adapter or third-party library (~half day)
Implement a port or consume RunIt without coupling to internals.
1. [Architecture](architecture/hexagonal-architecture.md) — the port boundary.
2. [Dependency graph](architecture/dependency-graph.md) — enforced edges.
3. [Runtime architecture diagram](diagram/runit-runtime.html) — components, request path, and the adapter trust boundary.
3. [Base contracts](concepts/base-contracts.md) + [semantic model](concepts/semantic-model.md).
4. [Third-party adapter recipe](recipes/third-party-adapter.md) + [conformance](conformance.md).
5. [ADR index](adr/README.md) — read the load-bearing decisions before publishing.

---

## Full map

| Area | Entry point | What it covers |
|---|---|---|
| **On-ramp** | [getting-started.md](getting-started.md) | Install, hello world, "what happened", where to go next. |
| **Tutorial** | [tutorial/](tutorial/01-hello-execution.md) | 7 progressive, build-up lessons mapped to the domain model. |
| **Concepts** | [concepts/semantic-model.md](concepts/semantic-model.md) | Canonical per-artifact reference (execution, activity, …). |
| **Architecture** | [architecture/](architecture/hexagonal-architecture.md) | Hexagonal design, dependency rules, lifecycle, publication, security, performance. |
| **Diagrams** | [diagram/runit-runtime.html](diagram/runit-runtime.html) | Interactive runtime architecture: components, primary request path, ports/adapters, and the trust boundary. |
| **Integrations** | [integrations/README.md](integrations/README.md) | OpenTelemetry, CloudEvents, PSR-3/14, HTTP, Swoole, Fibers, failure policies. |
| **Recipes** | [recipes/](../README.md#recipes) | Task-oriented snippets: CLI, HTTP, queue, worker, scheduler, testing, … |
| **Use cases** | [use-cases.md](use-cases.md) | Fit, decision guides, and "RunIt vs X". |
| **Ecosystem** | [ecosystem.md](ecosystem.md) | This package, the third-party sample, ConfigureIt, future bridges. |
| **ADRs** | [adr/README.md](adr/README.md) | 24 decision records + a principles summary. |
| **Releases** | [releases/1.0.0-rc.1.md](releases/1.0.0-rc.1.md) | What shipped in the release candidate. |
| **Compat / upgrade** | [compatibility.md](compatibility.md), [UPGRADING.md](UPGRADING.md) | Baseline, BC policy, upgrade steps. |
| **Conformance** | [conformance.md](conformance.md) | Framework-neutral conformance harness. |
| **QA / Security** | [QA.md](QA.md), [SECURITY.md](SECURITY.md), [security/](security/) | Quality gates and threat model. |
| **Glossary** | [glossary.md](glossary.md) | Every term, linked to its concept page and vocabulary IRI. |
| **Vocabulary** | [vocabulary.md](vocabulary.md) | The canonical OWL ontology + SKOS taxonomy, namespaces, and how code links to IRIs. |
| **Examples** | [`examples/`](https://github.com/it-pack/run-it/tree/main/examples) | 18 runnable scripts; each tutorial/recipe links the one it demonstrates. |
| **Roadmap** | [ROADMAP.md](ROADMAP.md) | Milestone history and post-1.0 plans. |

> Historical planning artifacts (reviews, past refactoring plans) live in
> [`archive/`](archive/) and are not part of the main reading path.

---

## Quick orientation by question

- *"What is an execution?"* → [concepts/execution.md](concepts/execution.md)
- *"How do I record a metric?"* → [tutorial/03-measure-and-observe.md](tutorial/03-measure-and-observe.md)
- *"How do errors behave?"* → [tutorial/06-nesting-and-flow.md](tutorial/06-nesting-and-flow.md)
- *"How do I send this to OpenTelemetry?"* → [integrations/opentelemetry.md](integrations/opentelemetry.md)
- *"Is my data safe / redacted?"* → [architecture/security.md](architecture/security.md)
- *"Can I extend RunIt?"* → [adr/README.md](adr/README.md) + [recipes/third-party-adapter.md](recipes/third-party-adapter.md)
