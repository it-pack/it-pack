# ADR-0002: Factories and Dependency Injection

## Status

Accepted.

## Context

Domain code must not construct its collaborators inline. Inline `new` of
adapters inside Core/services makes the graph untestable and hides wiring. The
It_Pack execution model and the hexagonal layout (ADR-0001) require collaborators
to be supplied from the composition root.

## Decision

- Every concrete class is built by a **factory**: a `final readonly class`
  implementing a `…FactoryInterface` port.
- A factory is **instantiated first**; it never creates product instances via a
  `static` method.
- The product is created via an instance method named **`build()`**.
- A factory MAY expose `static function create(...): self` that proxies its own
  constructor (so the composition root can build a factory without the `new`
  keyword). `create()` returns the factory, never the product.
- Default wiring lives in `Factory\DisenchanterFactory` and friends; a consumer
  may substitute any port implementation.

## Consequences

- Core never calls `new` on a collaborator; only the Factory namespace does.
- Tests inject mock ports through the factory or directly through the port
  interface.
- Adding behavior is an open/closed change: add an adapter + register it in a
  factory (ADR-0004 keeps the Factory able to depend on `Adapter`/`Core`).

## References

- `docs/REFACTORING_PLAN.md` §0
- `src/Factory/*.php`
