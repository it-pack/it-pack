# ADR-0003: PHP 8.4 Feature Usage

## Status

Accepted.

## Context

The project targets `php >= 8.4`. Modern language features can improve clarity
and immutability, but must not be used decoratively.

## Decision

Use the following features, only where they earn their place:

- **`readonly` classes / properties** for stateless services and immutable
  carriers (every `ValueObject`, every `Adapter` service).
- **Property hooks** for validated/config-derived projections, e.g.
  `SemConvConfig::$outputPath` (virtual `get` over an optional override) and the
  `*{ get; }` surfaces declared on value-object interfaces.
- **Backed enums** for genuinely closed internal state sets — `LiteralKind`,
  `LiteralRole`, plus the shared `SemConv` constants holder (an `interface`, not
  an enum, because its constants are unrelated variants, not mutually-exclusive
  cases).
- **First-class callables** (`StringLiteral::value(...)`) instead of closures
  where a single method is passed.
- **Native parameter / return / property types** everywhere; no implicit `mixed`.
- **Lazy objects / `WeakMap`** for the optional parser cache keyed by a
  `SourceCode` value object (deferred, optional — ADR preserves vendor-free
  first-use init for now).

Do **not** use a feature merely because it is available, and never to replace
domain semantics with a framework construct.

## Consequences

- Immutability is the default for carriers and context (the It_Pack execution
  model: "context is immutable; changes produce a new context").
- Closed sets are compiler-checked.
- Code stays readable to contributors who know modern PHP.

## References

- `docs/REFACTORING_PLAN.md` §7, §15
- `src/ValueObject/SemConvConfig.php`, `src/ValueObject/LiteralRole.php`
