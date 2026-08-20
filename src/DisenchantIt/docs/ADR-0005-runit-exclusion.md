# ADR-0005: RunIt Exclusion from Core

## Status

Accepted.

## Context

It_Pack is moving toward RunIt as a future base execution layer. DisenchantIt
must be *shaped* for that model now (context/activity/occurrence/snapshot
vocabulary) but must not take a RunIt dependency yet. Importing
`ItPack\RunIt\*` inside Core would couple the domain to an unreleased adapter
and break the hexagonal boundary (ADR-0001).

## Decision

- `Core` (and all of DisenchantIt) **never** imports `ItPack\RunIt\*`.
- The PHPArkitect rule `Core → not ItPack\RunIt\*` enforces this in CI.
- RunIt integration lands later as a **separate bridge package**
  (`it-pack/run-it-disenchant-it`, per RunIt ADR-016) that maps
  `DisenchantContext` / `DisenchantOccurrence` / `DisenchantObservation` /
  `DisenchantMeasurement` / `DisenchantSnapshot` onto RunIt's `Execution`
  aggregate. Core is unchanged by that bridge.
- DisenchantIt expresses execution semantics through its own port
  (`Contract\ExecutionRecorderInterface`) and immutable value objects under
  `ValueObject\Execution\`, so a RunIt adapter can translate without the domain
  ever knowing RunIt exists.

## Consequences

- Core stays RunIt-free and independently testable.
- The execution vocabulary is stable and addressable regardless of the future
  base layer.
- A future bridge is additive, not a refactor of Core.

## References

- `docs/REFACTORING_PLAN.md` §17
- `docs/EXECUTION.md`
- `src/Contract/ExecutionRecorderInterface.php`
- `src/ValueObject/Execution/*`
