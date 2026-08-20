# ADR-0002: Interface-per-trait composition

- **Status:** Accepted
- **Date:** 2026-08-15

## Context

A single flat interface for a class that composes many traits collapses unrelated
behaviour into one contract, violating Interface Segregation. Users want to depend
on the slice of behaviour a trait provides.

## Decision

Provide a per-trait mode (`PerTraitInterfaceDeriver`) that derives one interface per
used trait from that trait's *own* public methods, then composes the main interface
so it `extends` exactly those derived trait interfaces. The main interface declares
only public methods not already provided by a trait.

## Consequences

- Conflict resolution: when a method name appears in multiple traits, the first
  trait in `getTraitsRecursively()` order owns it; the others drop it. This
  guarantees no duplicate declaration across the extended interfaces.
- Traits with no public methods are skipped, so the main interface never extends an
  empty interface.
- The leakage invariant is preserved: the main interface `extends` only derived
  trait interfaces, never the adaptee hierarchy.
