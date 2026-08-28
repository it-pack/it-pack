# DeriveIt — User Guide

DeriveIt (`it-pack/derive-it`) derives **interfaces (and adapters)** from
third-party or legacy PHP classes — without hand-writing reflection code, and
without leaking the adaptee's own contracts into your codebase.

This guide is the in-depth, user-facing documentation. It complements the
[README](../../README.md) (quick start) and the [architecture decisions](../architecture/decisions/)
(ADRs). Runnable, copy-pasteable demonstrations live in
[`examples/`](../../examples/README.md).

## Contents

- [Tutorial](tutorial.md) — a hands-on, end-to-end walkthrough (adaptee → port → adapter → fake → CI guard).
- [Cookbook](cookbook.md) — advanced adoption patterns: family derivation, the adapter lifecycle, drift detection, the leakage auditor, telemetry, performance.
- [Features](features.md) — what DeriveIt can do, in depth.
- [Architecture](architecture.md) — how it is built and why.
- [Usage Guide](usage.md) — task-oriented recipes and the public API reference.

## Why DeriveIt exists

You want to depend on a **port** — an interface you own — not on someone else's
concrete class or someone else's interface. Hand-deriving a correct interface
from a real-world class (its full inheritance chain, its traits, its already
implemented interfaces, its constructor promotion, its union/intersection
types, its enums) is tedious and brittle to maintain as the adaptee changes.

DeriveIt automates that derivation: point it at a class (or a package, or a
related family of classes), and it walks the full class/trait/interface graph
and produces a clean, self-contained interface.

## Core concepts

### Port

A *port* is the interface you own and depend on. DeriveIt produces one interface
(or a small family of them) per adaptee, named `<Adaptee>Interface`.

### Leakage invariant

A derived interface must never reference the adaptee's own contracts. Concretely:

- In **flat** mode the interface has an **empty `extends`** and emits no `use`/
  FQCN leaking the adaptee's parent classes, interfaces, or traits.
- In **per-trait** mode the main interface `extends` **only** the derived trait
  interfaces, never the adaptee hierarchy.
- Types are rewritten (see below) so a method returning a sibling type points at
  its derived interface, not the concrete class.

The invariant is enforced in code (`InterfaceDeriver::assertSelfContained()` and
`PerTraitInterfaceDeriver::assertPerTraitSelfContained()`), not just by
convention.

### Canonical type representation

Internally, every type is stored as a **fully-qualified class name (FQCN)**.
When the interface is rendered to source, `nette/php-generator` simplifies FQCNs
to clean short names. This keeps the in-memory `InterfaceType` objects and the
rendered source unambiguous and consistent. See
[ADR-0004](../architecture/decisions/ADR-0004-type-substitution.md).

### Building blocks

DeriveIt has two layers:

1. **Graph walking** — `ClassReflection` plus three recursive resolvers walk the
   parent/interface/trait graph. This is the foundation every generator builds on.
2. **Generation** — the derivers turn the walked graph's public surface into
   `nette/php-generator` `InterfaceType` (and `ClassType` for adapters).

## Quality gates

DeriveIt ships green on every gate (see the README status table):

| Gate | Command |
|------|---------|
| Unit tests | `composer test:unit` |
| Coding standard | `composer cs:check` |
| Static analysis | `composer analyse` |
| Architecture (deptrac) | `composer architecture` |
| Mutation testing | `composer infection` |
