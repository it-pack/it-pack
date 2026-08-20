# ADR-0001: Ports & Adapters (Hexagonal) Architecture

## Status

Accepted.

## Context

DisenchantIt must stay vendor-neutral, framework-neutral, and testable in
isolation. Detection, placement, rewriting, and SemConv collection are domain
behavior; the nikic parser, Nette generator, and future telemetry/execution
integrations are infrastructure. Coupling domain logic to any one adapter
(parser, formatter, OTel, RunIt) would make the library harder to test and to
extend.

## Decision

Use a hexagonal (ports-and-adapters) layout with these namespaces:

- `Contract\` — ports: interfaces only, depending only on other interfaces or
  closed enums. No concrete classes, no vendor types.
- `ValueObject\` — immutable carriers; every concrete carrier implements a
  matching interface (see ADR-0004).
- `Core\` — the `Disenchanter` orchestrator; depends only on `Contract` and
  `ValueObject`. Never on `Adapter` or any vendor type.
- `Adapter\` — concrete infrastructure (Detector, LineBasedRewriter, collectors,
  visitors, strategies, the no-op `NullExecutionRecorder`). Depends on
  `Contract`, `ValueObject`, and itself; never on `Core`.
- `Factory\` — composition root; the only place allowed to wire concrete
  `Adapter`/`Core` classes together (ADR-0002).

Adapter code translates DisenchantIt concepts to/from vendor types but never
changes their meaning.

## Consequences

- Domain invariants (notably the source-text safety hard rule) are isolated from
  infrastructure.
- New adapters (OpenTelemetry, CloudEvents, RunIt) slot in without touching Core.
- Every concrete class is mockable behind a port.

## References

- `docs/REFACTORING_PLAN.md` §16
- `phparkitect.php` (layered `where(...)->mayDependOnComponents(...)` rules)
