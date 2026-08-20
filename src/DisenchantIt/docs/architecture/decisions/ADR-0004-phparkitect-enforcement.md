# ADR-0004: PHPArkitect as Architecture Enforcement

## Status

Accepted.

## Context

The layered boundaries in ADR-0001 are easy to violate by accident as the
codebase grows. Docblock prose is not enforced. We need the dependency
direction and class-shape rules checked automatically in CI, not by review.

## Decision

`phparkitect.php` is an authoritative gate (`composer architecture`). It encodes:

1. **Layered components** (`Architecture::withComponents()`):
   - `Contract`, `Core`, `Adapter`, `ValueObject`, `Factory`.
   - `Contract → ValueObject`; `Core → Contract, ValueObject`;
     `Adapter → Contract, ValueObject, Adapter`;
     `Factory → Contract, ValueObject, Factory, Adapter, Core`;
     `ValueObject → ValueObject`.
   - The Factory component is the composition root, so it legitimately wires
     concrete `Adapter`/`Core` classes (ADR-0002).
2. **Interfaces depend only on interfaces** (custom `Expression`): an interface
   may depend on other interfaces, on our closed enums (`LiteralKind`,
   `LiteralRole`), and on external types, but not on our own concrete classes.
3. **Concrete classes are `final`** (composition over inheritance).
4. **Final classes are backed by an interface** (`finalClassesMustBeBackedRule`):
   every concrete class implements a port, so it is mockable. Exceptions
   (implement `Throwable`) and enums are exempt.
5. **Factories are `final`** and named `*Factory`.
6. **`Core` must not depend on `ItPack\RunIt\*`** (ADR-0005).

Violations are **fixed at the dependency direction**, never by adding skips.

## Consequences

- The dormant layered rules activate as soon as the namespaces exist, turning
  the gate from advisory to enforcing.
- Architecture regressions fail CI before they merge.
- New contributors get immediate, mechanical feedback on boundary mistakes.

## References

- `phparkitect.php`
- `docs/REFACTORING_PLAN.md` §16
