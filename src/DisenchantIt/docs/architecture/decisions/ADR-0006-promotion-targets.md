# ADR-0006 — Promotion targets: const vs backed-enum vs flag-set vs shared

- **Status:** Accepted
- **Date:** 2026-08-22
- **Supersedes / relates:** ADR-0003 (closed internal state → backed enums),
  ADR-0011 (execution recorder / occurrence feed). Referenced by
  `docs/ADDITIONAL_FEATURES_ROADMAP.md` §S6.2.

## Context

DisenchantIt detects groups of repeated magic scalars and promotes each group to
named code. The *shape* of the promotion is not uniform:

- A single repeated literal in one class should become a `private const`.
- A repeated literal that is one member of a *closed set* (an enum-like tags/keys
  signal) should become a backed enum case, not N unrelated constants.
- A repeated integer bitmask (power-of-two, sitting in `|`/`&`) should become a
  set of flag constants — not an equally-valid-but-less-intentional enum.
- An FQCN string can (opt-in) become `<Short>::class` with no declaration.
- A value collected into the shared SemConv registry should stay a constant but
  be *aliased through* to the shared name.

Before Phase 2 these decisions were hard-coded inside per-class placement
strategies (`SameClassConstantStrategy` / `PerClassConstantStrategy` / the old
`AutoConstantPlacementStrategy`, now `CompositeConstantPlacementStrategy`). That
made "add a new promotion shape" a change to the orchestrator, violating the
open/closed expectation of the hexagonal design (AGENTS.md §Architectural
Boundaries).

We need a single, stable seam that decides *how* a detected group is promoted and
emits the edit set, so that new promotion shapes are additive.

## Decision

### 1. A single port: `PromotionTargetInterface`

Every promotion shape implements `Contract\PromotionTargetInterface`:

```php
interface PromotionTargetInterface
{
    public function supports(GroupedCandidateInterface $group): bool;
    public function build(GroupedCandidateInterface $group): PromotionArtifactInterface;
}
```

- `supports()` is a pure predicate over an immutable `GroupedCandidateInterface`
  (already carries `isClosedSet`, `proposedEnumName`, the grouped occurrences,
  and the recovered `NamingContext`).
- `build()` returns one immutable `PromotionArtifactInterface` (insertions +
  replacements + a `TargetKind`) — the same carrier the `LineBasedRewriter`
  already applies. **No target reprints AST or touches the filesystem.**

This keeps the dependency direction correct: `Adapter` → `Contract` +
`ValueObject` only. Core depends on `PromotionTargetInterface`, never on a
concrete target.

### 2. A composite, first-match-wins

`Adapter\CompositePromotionTarget` holds an ordered `iterable` of targets and
delegates each group to the **first** target whose `supports()` returns true.
Adding a promotion shape is therefore an open/closed change: implement the port
and register it in the factory — no edit to `Core\Disenchanter`.

### 3. The composition root: `PromotionTargetFactory`

`Factory\PromotionTargetFactory` (a `final readonly` factory implementing
`Contract\PromotionTargetFactoryInterface`, with `build()` producing the
composite and an optional `static create()` proxy per AGENTS.md) assembles the
target list **in priority order**:

1. `ClassnameToClassConst` — *only* when `convertFqcnToClassConst` is enabled
   (opt-in `::class` converter, Phase 4). Emits no declaration.
2. `FlagSetTarget` — bit-flag power-of-two sets (Phase 3, S3.2).
3. `BackedEnumTarget` — closed sets.
4. `ClassConstantTarget` — the general fallback (today's `private const`
   behavior).

Priority is deliberate: a power-of-two set is promoted to **flags** rather than
an (equally-valid-but-less-intentional) enum, because FlagSetTarget precedes
BackedEnumTarget. Closed sets beat the general `const` fallback. FQCN conversion
runs first only because it is an explicit opt-in escape hatch for a distinct
input kind.

### 4. The closed-set detection policy (the enum signal)

A group is flagged `isClosedSet` by `Adapter\ClosedSetDetector` (implementing
`Contract\ClosedSetDetectorInterface`). The policy:

- A group is a closed set **only** when it is a repetition/enum-member signal —
  the same rule family that drives candidate detection (AGENTS.md §Domain
  Invariants): identifier-shaped literals, no sentence punctuation, and (for
  numerics) a recoverable naming hint from the surrounding `NamingContext`.
- A backed enum is emitted **only** for string/int literals that survive the
  candidate filter. A backed enum case's own backing value is excluded from
  re-detection (role `EnumBackingValue`), preserving idempotency on a second
  pass.
- The backing type is derived from `LiteralKind`: `int` → `int`, otherwise
  `string`.
- Trivial scalars (`0`, `1`, `0.0`, `1.0`) and lone values are **never** promoted
  to an enum — a closed set requires the member signal, not a single occurrence.

This is the ADR-0003 rule ("closed internal state sets are backed enums")
operationalized through detection, not through a hardcoded branch.

### 5. `TargetKind` is the closed state enum

`ValueObject\TargetKind` is a backed `enum` (per AGENTS.md: genuinely closed
internal state). Its cases `Const`, `Enum`, `FlagSet`, `Fqcn`, `SharedConst`
are carried on every `PromotionArtifact` so recorders/observers and the
dry-run planner can classify what was emitted without re-deriving it.

### 6. Idempotency is a target invariant

Each target is idempotent by construction:

- `BackedEnumTarget` / `FlagSetTarget`: a second pass never re-finds the raw
  literal (already rewritten to `<Name>::<Case>` / `self::<Flag>`), and backing
  values are filtered out.
- `ClassConstantTarget`: re-declaration is prevented (the constant already
  exists on the class → no new insertion; surviving raw occurrences are still
  replaced).
- `ClassnameToClassConst`: already-rewritten `<Short>::class` is not a string
  literal and is not re-matched.

No target mutates shared/analyzed files in place; the rewriter applies the
artifact exactly on the recorded lines.

## Consequences

### Positive

- New promotion shapes are additive (implement the port, register in the
  factory). `Core\Disenchanter` is unchanged when a target is added.
- The seam is vendor-neutral and framework-neutral; it only depends on contracts
  and immutable value objects.
- `TargetKind` gives recorders (JSON/SARIF, ADR-0011) and the dry-run planner a
  stable classification dimension.
- Closed-set promotion follows the detection policy, not a hardcoded `< 2`
  branch, satisfying ADR-0003.

### Negative / costs

- Target ordering inside the composite is now a behavioral contract; reordering
  can change which shape a group receives (e.g. flags vs enum). This ordering is
  fixed by `PromotionTargetFactory` and documented there.
- Each target must independently honor the source-text safety hard rule (no
  execute/autoload of analysed files) — already enforced because targets only
  build immutable artifacts.

### Backward compatibility

- `PromotionTargetInterface` is new public API; adding a method later would break
  third-party implementations, so future extensions should add a *new focused
  interface* and compose it (per AGENTS.md §Backward Compatibility).
- Existing `const` behavior is preserved as the `ClassConstantTarget` fallback,
  so default promotion output is unchanged for previously-supported inputs.

## Future / open

- `TargetKind::SharedConst` is reserved for the SemConv-aliased shared constant
  promotion (Phase 4 SemConv collector already layers the alias through; the
  target itself is a future additive step via this same seam).
- A shared-const target would implement `PromotionTargetInterface` and register
  **ahead of** `ClassConstantTarget` when SemConv aliasing is enabled, reusing
  `TargetKind::SharedConst` for classification.

## References

- `src/Contract/PromotionTargetInterface.php`
- `src/Contract/PromotionTargetFactoryInterface.php`
- `src/Adapter/CompositePromotionTarget.php`
- `src/Factory/PromotionTargetFactory.php`
- `src/Adapter/ClassConstantTarget.php`, `BackedEnumTarget.php`,
  `FlagSetTarget.php`, `ClassnameToClassConst.php`
- `src/ValueObject/TargetKind.php`, `GroupedCandidateInterface.php`
- `docs/REFACTORING_PLAN.md` (§14 OCP strategy registry, §17 execution model)
- AGENTS.md §Architectural Boundaries, §Domain Invariants, §Factories
