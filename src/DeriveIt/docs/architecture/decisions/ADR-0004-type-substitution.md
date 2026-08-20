# ADR-0004: Type-substitution strategy

- **Status:** Accepted
- **Date:** 2026-08-18

## Context

When deriving a family of related classes together (e.g. `Node` ⇄ `Tree`), a
derived method's return/parameter type may itself be a class being derived. Leaving
the native type in the derived interface defeats the purpose — the port would still
leak a concrete adaptee type.

## Decision

- Store types internally as **FQCNs** (the canonical form). Nette's
  `PhpNamespace` simplifies FQCNs to clean short names in rendered source, so the
  emitted code is correct and readable.
- Apply substitution through `TypeSubstitutor`, built from
  `DeriveConfig::typeSubstitutionMap` (class FQCN => derived interface FQCN). The
  substitutor returns the mapped FQCN verbatim, so in-memory `InterfaceType` objects
  and rendered source agree.
- For family derivation, `FamilyDeriver` auto-detects cross references by declared
  type match (default), with explicit caller-supplied overrides for anything
  ambiguous.

## Consequences

- A derived `NodeInterface::getNext(): ?NodeInterface` references the derived
  interface, not the native `Node` — the leakage invariant holds across the family.
- Self/static/parent return types render as the literal class name (Nette
  resolution); intentional substitution covers the derived-family case.
- Bare short names are rejected as the internal form because Nette cannot render
  them without a leading `\`; FQCN-internal keeps both in-memory types and rendered
  source unambiguous.
