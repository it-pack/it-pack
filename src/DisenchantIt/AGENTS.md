# AGENTS.md

## Purpose

This file guides AI coding agents working on **DisenchantIt** (`it-pack/disenchant-it`).

DisenchantIt is a library and SDK that finds and replaces magic scalar values in PHP
source — most often repeated string literals or numeric literals used as type tags, keys, enum-like members,
or configuration tokens — promoting them to explicit `private const` declarations (and,
eventually, backed enums) without ever executing or autoloading the files it analyses.

Use these instructions when implementing, reviewing, refactoring, testing, or documenting
the project. Keep changes small, explicit, and consistent with the It_Pack execution
model and the hexagonal architecture laid out in `docs/REFACTORING_PLAN.md`.

---

## Instruction Priority

Follow instructions in this order:

1. The current user or task request.
2. Repository-local instructions in the nearest `AGENTS.md`.
3. This file.
4. Existing architecture decisions and public contracts.
5. Existing code conventions.
6. General language and framework conventions.

If instructions conflict, preserve the higher-priority intent and make the smallest necessary adjustment.

Do not silently resolve significant ambiguity. State the issue and request clarification when it affects public APIs, domain semantics, security, or backward compatibility.

---

## Project Mission

DisenchantIt turns magic scalar values into named constants:

```text
Source text (PHP)
    │
    ▼
Detect   ── AST scan of string and numeric literals, classify role, filter candidates
    │
    ▼
Group    ── by literal value (repetition = enum-member signal)
    │
    ▼
Place    ── decide where each constant lives (SameClass / PerClass / Auto)
    │
    ▼
Rewrite  ── line-based insertion + literal → self::CONST replacement
    │
    ▼
SemConv  ── optional shared constant registry, aliased through by classes
```

DisenchantIt is a participant in the broader It_Pack ecosystem and follows the shared
**execution model** defined in `docs/EXECUTION.md`:

```text
Execution
├── Context
├── Activity
├── Measurement
├── Observation
├── Occurrence
└── Snapshot
```

The governing principles are:

> Everything happens within an execution.
> Execution carries context.
> Activities perform work.
> Measurements quantify work.
> Observations describe work.
> Occurrences record work.
> Snapshots preserve work.

External technologies implement or transport parts of this model:

- OpenTelemetry is an adapter.
- CloudEvents is an adapter.
- PSR-3 is an adapter.
- PSR-14 is an adapter.
- RunIt (WiP) is the future base execution layer for all it-pack packages.

DisenchantIt must be *shaped* for that model now (see `docs/REFACTORING_PLAN.md` §17) but
must **not** take a RunIt dependency yet. Never redefine It_Pack domain semantics in
terms of an external standard.

---

## Sources of Truth

Before making architectural changes, read the smallest relevant set of:

1. `docs/EXECUTION.md`
2. `README.md`
3. `docs/REFACTORING_PLAN.md` (the active polish/refactor plan; carries ADR-0001 … ADR-0005)
4. Public contracts under `src/Contract/` (created during the refactoring)
5. Tests covering the affected behavior (Codeception suites under `tests/`)
6. `composer.json`
7. `phparkitect.php` (architecture rules — replaces Deptrac)
8. `phpstan.neon` / `phpstan.neon.dist`

If a listed file does not exist, do not invent its contents. Use the available sources and
mention the missing artifact when it blocks the task.

Public contracts, accepted ADRs (recorded in `docs/REFACTORING_PLAN.md`), and tested
behavior take precedence over incidental implementation details.

> Note: the initial extraction from `var/lab` PoC is **Phase-0** — a flat `src/` of
> PSR-4 classes. `var/` is gitignored; `var/lab` holds the PoC scripts and
> `var/test-code` holds a copied real project used as a tinker target. Do not promote
> PoC/var files into `src/` as-is; extract artifacts per `docs/REFACTORING_PLAN.md`.

---

## Context-Efficient Workflow

Agents with any context-window size should work incrementally.

### Before editing

1. Read this file.
2. Inspect the requested area and its immediate dependencies.
3. Find existing contracts, implementations, tests, and documentation.
4. Identify whether the change affects:
   - Domain semantics (the detect/rewrite hard rule).
   - Public API.
   - Serialization.
   - Adapter mapping.
   - Security.
   - Performance.
   - Backward compatibility.
5. Form a short implementation plan.

Do not load the entire repository unless the task genuinely requires it.

### While editing

- Make one coherent change at a time.
- Prefer modifying existing focused components over creating parallel abstractions.
- Keep patches small enough to review.
- Re-check relevant tests after each meaningful step.
- Preserve unrelated formatting and behavior.
- Do not refactor unrelated code opportunistically.

### Before finishing

1. Review the diff.
2. Remove accidental or speculative changes.
3. Run the narrowest relevant checks.
4. Run broader quality checks when available and proportionate.
5. Update tests and documentation.
6. Report what changed, what was validated, and any unresolved risk.

If context is limited, prioritize correctness in this order:

1. Domain invariants (never execute/autoload analysed files).
2. Public contracts.
3. Tests.
4. Security.
5. Architecture boundaries.
6. Documentation.
7. Internal cleanup.

---

## Architectural Boundaries

DisenchantIt uses hexagonal ports-and-adapters architecture. The planned layout
(`docs/REFACTORING_PLAN.md` §16) is:

```text
CLI / Consumer Adapters
        │
        ▼
Core (Disenchanter orchestration)
        │
        ▼
Contract (ports) ◄── Adapter (implementations)
        │
        ▼
ValueObject (immutable carriers)
```

Expected dependency direction:

```text
Contract    → (nothing internal; only PHP/std)
ValueObject → (nothing internal)
Core        → Contract, ValueObject
Adapter     → Contract (+ vendor, e.g. nikic/php-parser), never Core
CLI         → Core, Contract, Adapter (thin)
```

### Contract must not depend on

- Core orchestration.
- Adapter implementations.
- Vendor packages (nikic/php-parser, nette/php-generator, rector/rector).
- OpenTelemetry, CloudEvents, PSR interfaces.
- Frameworks, containers, filesystems, network clients.
- `ItPack\RunIt\*` (RunIt is a future adapter, never a Core dependency — ADR-0005).

### Core must not depend on

- Concrete adapter classes.
- Vendor types in its signatures.
- `ItPack\RunIt\*`.

### Adapter rules

Adapters may translate DisenchantIt concepts (e.g. nikic AST ↔ `StringLiteral`) but must
not change their meaning. Keep mapping logic inside the relevant adapter. Do not add
vendor-specific fields to core domain objects solely to satisfy an adapter.

PHPArkitect rules are architectural requirements, not optional style guidance
(`docs/REFACTORING_PLAN.md` §16). They enforce the layered components above, plus:
"interfaces depend only on interfaces", "classes are final", and "final classes are
backed by interfaces" — the last two guarantee every concrete class implements a port
(composition over inheritance).

---

## Domain Invariants

Preserve these invariants unless an accepted ADR explicitly changes them.

### Source-text safety (the hard rule)

- DisenchantIt **never** `require`s, `include`s, `class_exists()`s, or otherwise
  executes/autoloads the PHP files it analyses or rewrites.
- Detection is pure source-text/parse-tree in, data-structure out.
- Rewriting is pure source-text in, source-text out; it does not re-evaluate the file.
- Verifying a rewrite (e.g. `php -l`, running the target's own test suite) is a *separate
  process* concern and stays out of DisenchantIt's runtime.

### Detection and placement

- A constant's own declaration value is never treated as a usage site (no self-rewrap).
- A backed enum case's value is never promoted to a separate constant (would fatal).
- Exception messages and `sprintf`/`printf` format strings are not candidates by default.
- Only identifier-shaped literals (no internal spaces / sentence punctuation) are
  candidates by default.
- Numeric literals (`LNumber` / `DNumber`, including raw tokens such as `0x1F`,
  `1_000_000`, `3.14`) are candidates **only** when a naming hint is recoverable from
  the surrounding `NamingContext` — a variable/property name, an array key, or a trailing
  unit comment like `// ms`.
- Trivial numeric values (`0`, `1`, `0.0`, `1.0`) are not candidates by default: they
  carry no distinguishing semantics without a naming hint.
- A numeric candidate is promoted to a **typed** constant (`private const int …` /
  `private const float …`) whose value preserves the literal's raw source token
  (`0x1F` stays `0x1F`, `1_000_000` stays `1_000_000`).
- A constant is never re-declared in a class that already declares it (idempotent).
- A literal replacement is scoped to the exact recorded line, so untouched lines stay
  byte-for-byte identical.

### SemConv

- A SemConv collection merge must not discard constants already collected by a prior run.
- Name collisions with *differing* values are a hard error, not a silent override.
- Aliasing a class-local constant to `SemConv::X` is idempotent (already-aliased lines
  are left untouched); existing `self::X` call sites are never modified.

### Execution model (inherits It_Pack EXECUTION.md)

- Context is immutable; changes produce a new context.
- Occurrences are immutable facts with **lowercase, stable, versioned** names
  (e.g. `disenchant.it.magic_value.detected.v1`), independent of PHP class names.
- Snapshots are immutable and serialize deterministically.
- Arbitrary PHP object serialization is prohibited.

---

## Execution Lifecycle

DisenchantIt runs as an **Activity within an Execution** (see `docs/REFACTORING_PLAN.md`
§17). The `Core\Disenchanter` is the orchestrating activity, structured into explicit
bounded steps (scan → decide → rewrite → collect-semconv → alias-semconv), each recorded
through an optional `ExecutionRecorderInterface` port whose default is a no-op.

Preserve the execution state machine when RunIt integration lands:

```text
CREATED
   │
   ▼
RUNNING
   ├──► SUCCEEDED
   ├──► FAILED
   ├──► CANCELLED
   └──► TIMED_OUT
```

Lifecycle behavior must be:

- Explicit.
- Validated.
- Idempotent where documented.
- Safe during exceptions.
- Testable without external infrastructure.

When an operation throws:

1. Record failure safely.
2. Complete the activity/execution as failed.
3. Restore prior context in `finally`.
4. Rethrow the original `Throwable`.

A telemetry or adapter exception must not replace the original business exception.

---

## PHP Standards

Target PHP `>= 8.4`.

Every PHP file must begin with:

```php
<?php

declare(strict_types=1);
```

### Classes and contracts

- Every concrete class must be `final`.
- Use `readonly` whenever controlled mutation is unnecessary.
- Every concrete class must implement an appropriate interface (port).
- Exceptions must implement the relevant `DisenchantItExceptionInterface` marker.
- Prefer composition over inheritance.
- Keep interfaces small and focused.
- Do not create interfaces solely to duplicate another interface without semantic value.
- Traits must harmonize with interfaces and remain focused.
- Avoid stateful traits and hidden dependencies.

### Factories (required pattern)

Per `docs/REFACTORING_PLAN.md` §0, collaborators are supplied via dependency injection
using factories — no inline `new` of collaborators inside domain code.

- A factory is **instantiated first**; it never creates product instances through a
  `static` method.
- The product is created via an instance method named **`build()`**.
- A factory MAY expose a `static function create(...): self` that **proxies its own
  constructor** (so the composition root can build a factory without the `new` keyword).
  `create()` returns the factory, never the product.
- Factories are `final readonly class` implementing a `…FactoryInterface` port.

### Types

- Use native parameter, property, and return types.
- Avoid implicit `mixed`.
- Add PHPDoc generics where native PHP cannot express a relationship.
- Do not use PHPDoc to contradict native types.
- Use dedicated value objects for identifiers, names, units, and validated concepts.
- Use `null` for optional values.
- Use property hooks for value objects and computed/config-derived properties.
- Prefer extensible validated strings over closed enums for user-defined names and kinds.
- Use enums for genuinely closed internal state sets (e.g. a `LiteralRole` backed enum).
- Ensure interfaces only depend on interfaces/traits and no concrete classes.

### PHP 8.4 features

Use modern features only when they improve clarity:

- Property hooks for clear validation or computed properties (e.g. `SemConvConfig`).
- Virtual properties for intentional projections.
- Lazy objects for expensive optional infrastructure (e.g. the nikic parser stack).
- Fibers through runtime adapters (e.g. per-file scan overlap of I/O and parsing).
- `WeakMap` for object-keyed, non-owning runtime caches (e.g. parsed-AST cache keyed by a
  `SourceCode` value object).
- `WeakReference` only where correctness does not depend on object lifetime.

Do not use a language feature merely because it is available.

---

## API Design

DisenchantIt is a library and SDK for both ItPack and third-party consumers.

Public APIs must be:

- Vendor-neutral.
- Framework-neutral.
- Explicitly typed.
- Composable.
- Testable.
- Difficult to misuse.
- Stable enough for independent adapters.

### Prefer

- Interfaces (ports) at architectural boundaries.
- Immutable definitions and artifacts.
- Factories for infrastructure assembly (`build()`, optional `create()` proxy).
- Immutable builders for ergonomic configuration.
- Decorators for non-intrusive instrumentation.
- Explicit scope arguments.
- Narrow recorder interfaces (e.g. `ExecutionRecorderInterface`).
- Ports for time, identity, serialization, and storage.

### Avoid

- Global facades.
- Service locators.
- Hidden container access.
- Mandatory inheritance.
- Reflection on hot paths.
- Generic "manager" or "helper" classes.
- Large configuration arrays without typed builders.
- Boolean parameters with unclear meaning.
- Public APIs coupled to one adapter.
- Proprietary promises or schedulers.

Do not add a public extension point until there is a concrete consumer or adapter use case.

---

## Naming

Use domain terminology consistently. DisenchantIt reuses the It_Pack execution vocabulary
and adds detection/rewrite terms:

| Use | Avoid as a synonym |
|---|---|
| Execution | Trace, transaction, request |
| Activity | Span, operation step |
| Measurement | Metric value |
| Observation | Log |
| Occurrence | Event |
| Snapshot | Serialized result |
| Context | Baggage, metadata bag |
| Magic value | Hard-coded scalar (string or numeric) literal |
| Candidate | Detected literal (string or numeric) worth promoting |
| Placement | Where a constant is declared + what it replaces |
| SemConv | Shared constant registry |

Adapter documentation may explain external mappings, but core APIs must retain DisenchantIt
vocabulary.

Names should communicate one responsibility:

```text
Detector
ConstantPlacementStrategy
LineBasedRewriter
SemConvCollector
Disenchanter
ConstantPlacementFactory
```

Avoid vague names such as:

```text
StringManager
RefactorHelper
DataProcessor
Utility
```

---

## External Adapter Rules (future)

These apply once DisenchantIt gains external integrations; they are recorded now so the
Core stays clean.

### OpenTelemetry

- Keep OpenTelemetry dependencies optional.
- Library code must not configure global providers.
- Map executions and activities without equating them to traces and spans.
- Test mapping with in-memory providers.

### CloudEvents

- Map occurrences through a dedicated mapper.
- Validate required attributes.
- Keep event data serialization explicit.

### PSR-3

- Map observations to logger messages and context.
- Keep message templates stable.
- Do not turn every log into an occurrence.

### PSR-14

- Account for synchronous dispatch and listener exceptions.
- Do not allow PSR event mutability to mutate immutable DisenchantIt occurrences.

### Swoole, OpenSwoole, and Fibers

- Store context per Fiber or coroutine.
- Never store request context in process-global mutable state.
- Restore or clear context in `finally`.

### RunIt (future base execution layer)

- A separate bridge package (`it-pack/run-it-disenchant-it`, per RunIt ADR-016) maps
  DisenchantIt's `DisenchantContext` / `DisenchantOccurrence` / `DisenchantObservation` /
  `DisenchantSnapshot` onto RunIt's `Execution` aggregate.
- DisenchantIt Core never imports `ItPack\RunIt\*`.
- PHPArkitect enforces this (`Core` must not depend on `ItPack\RunIt\*`).

---

## Publication and Failure Policy

Artifact (occurrence/snapshot) publication, when added, follows this conceptual pipeline:

```text
Validation
→ Enrichment
→ Classification
→ Redaction
→ Sampling
→ Routing
→ Adapter publication
```

Requirements:

- Redact before external publication.
- Bound buffers and queues.
- Prevent recursive publication.
- Preserve the business result and the original business exception.
- Make fail-closed behavior explicit.

Default behavior is expected to be fail-open with fallback reporting. Never catch and
silently discard a publication failure without recording it through a health path.

---

## Security Requirements

Treat source files, observations, occurrences, and snapshots as potential data paths.

Do not:

- Export secrets found in analysed source beyond what the rewrite requires.
- Serialize arbitrary objects.
- Export unlimited stack traces.
- Create unbounded attributes or payloads.
- Use user-controlled values as metric names.
- Use high-cardinality values as metric dimensions by default.

When adding fields or mappings, consider: classification, redaction, maximum size,
cardinality, serialization safety, tenant isolation, propagation boundaries.

Security-sensitive behavior requires negative tests.

---

## Performance Requirements

Assume detection/rewrite may run over large source trees.

Avoid:

- Network or filesystem operations in core services (discovery is an injected port).
- Reflection during normal execution.
- Eager serialization.
- Retaining all artifacts by default.
- Unbounded recursion, nesting, or buffering.
- Per-artifact initialization of expensive adapters (e.g. build the nikic parser once).

Prefer:

- Monotonic clocks for durations.
- Deferred normalization.
- Reusable immutable mappers and policies.
- Bounded buffering.
- `WeakMap` caches keyed by a `SourceCode` value object.
- Lazy objects for the parser stack.
- Sampling/filtering before expensive work.

Performance changes require benchmarks when they affect scan or rewrite throughput. Do not
claim a performance improvement without measurements.

---

## Testing Requirements

Every behavior change requires tests at the narrowest appropriate level. DisenchantIt uses
**Codeception** for its test suites (`codeception.yml`).

### Test categories

- Domain invariant tests (the source-text safety hard rule, idempotency).
- Port contract tests (mock the port; assert behavior).
- Adapter integration tests (fixture PHP source strings; nikic-based detector;
  line-based rewriter; SemConv collector/alias rewriter).
- Serialization round-trip tests (snapshot / SemConv output is deterministic).
- Failure-injection tests (parse errors, SemConv collisions).
- Property-based tests where useful.
- Fiber or coroutine isolation tests (once Fibers are used).

### Critical cases

Always test relevant combinations of:

- Successful detection + rewrite (idempotent on a second pass).
- Self-rewrap avoidance (a constant's own value is not re-promoted).
- Enum backing value exclusion.
- Constant already present (no duplicate declaration).
- SemConv merge with an existing file (prior constants preserved).
- SemConv name collision with differing values (hard error).
- Original operation failure (exception preserved, file left untouched in dry-run).

A test should verify observable behavior, not private implementation details.

---

## Quality Gates

Inspect `composer.json` before running commands. Use repository-defined scripts when
available.

Expected tools include:

- PHPStan (static analysis).
- PHPArkitect (architecture rules — replaces Deptrac).
- PHP-CS-Fixer (coding style).
- Infection (mutation testing).
- Composer validation and audit.
- Backward compatibility checks.
- Codeception unit / integration test runners.

Typical checks may include:

```bash
composer validate
composer test
composer analyse
composer architecture
composer coding-style
composer mutation
composer audit
composer quality
```

Do not assume these scripts exist. Verify them first.

### PHPStan

- Target the highest configured level.
- Do not add first-party errors to a baseline.
- Do not suppress an error without a specific documented reason.
- Prefer correcting types and design over adding broad ignores.

### PHPArkitect

- Do not add skips to hide an architectural violation.
- Correct the dependency direction.
- If a boundary must change, update the architecture (REFACTORING_PLAN §16) and the ADR
  first.

### Mutation testing

Prioritize mutation coverage for:

- Placement decisions.
- Failure policies.
- Idempotency guards.
- Validators.
- Adapter mappings (rewriter line edits).

---

## Documentation Requirements

Update documentation when changing:

- Public APIs.
- Domain semantics (the detect/rewrite hard rule).
- Lifecycle behavior.
- Serialization.
- Occurrence naming.
- Adapter mappings.
- Failure policy.
- Security defaults.
- Configuration.
- Installation requirements.

Examples must:

- Use current public APIs.
- Include `declare(strict_types=1)`.
- Be minimal but executable where practical.
- Avoid imaginary framework setup.
- Demonstrate DisenchantIt terminology rather than adapter terminology.

Create or update an ADR (recorded in `docs/REFACTORING_PLAN.md`) when a change affects:

- Aggregate boundaries.
- Dependency direction.
- Public extension points.
- Lifecycle semantics.
- Failure guarantees.
- Context propagation.
- Package splitting.
- Security defaults.
- Serialization compatibility.

---

## Backward Compatibility

Before `1.0.0`, breaking changes may be necessary but must be deliberate and documented.

After `1.0.0`, treat these as compatibility-sensitive:

- Public interfaces and signatures.
- Public classes and enums.
- Artifact normalization fields.
- Serialized schemas marked stable (SemConv file shape).
- Occurrence type conventions.
- Adapter extension contracts.
- Exception types exposed to consumers.

Do not casually add methods to public interfaces. Adding an interface method breaks
third-party implementations. Prefer introducing a new focused interface and composing it
into new APIs.

---

## Change Discipline

### Do

- Solve the requested problem directly.
- Reuse established domain language.
- Add tests before or with behavior changes.
- Preserve original exceptions.
- Keep adapters optional.
- Use ports for external effects.
- Explain non-obvious decisions.
- Update affected documentation.

### Do not

- Rewrite unrelated code.
- Add dependencies without justification.
- Introduce a framework requirement.
- Introduce global mutable state.
- Expose adapter types from domain contracts.
- Add speculative abstractions.
- Hide errors with broad static-analysis ignores.
- Disable architecture rules.
- Regenerate baselines to make CI pass.
- Change serialized formats (SemConv) accidentally.
- Execute or autoload analysed files.
- Commit generated artifacts unless the repository requires them.
- Claim checks passed when they were not run.

---

## When to Stop and Ask

Request clarification before proceeding when:

- `docs/EXECUTION.md`, an ADR, and public code disagree materially.
- A change would break a stable public contract.
- Required security or data-classification behavior is unknown.
- A new dependency or package split is required.
- The task requires choosing between incompatible execution semantics.
- Existing tests encode contradictory behavior.
- The requested change would couple the domain to an external adapter (including RunIt).
- Required project files or specifications are missing.

If clarification is unavailable, present:

1. The unresolved question.
2. The smallest safe assumption.
3. The consequences of that assumption.
4. The reversible implementation option.

---

## Completion Report

When returning a completed implementation task, report concisely:

- What changed.
- Why it changed.
- Tests and quality checks run.
- Checks not run and why.
- Public API or compatibility impact.
- Remaining risks or follow-up work.

Do not include large code dumps when the changes already exist in the working tree.

---

## Final Review Checklist

Before considering a change complete, verify:

- [ ] The change follows DisenchantIt and It_Pack execution vocabulary.
- [ ] Domain semantics remain vendor-neutral.
- [ ] The source-text safety hard rule is preserved (no execute/autoload of analysed files).
- [ ] Dependency direction is correct (Contract → nothing; Core → Contract/ValueObject;
      Adapter → Contract only; `ItPack\RunIt\*` never imported by Core).
- [ ] Concrete classes are `final`.
- [ ] Eligible classes are `readonly`.
- [ ] Concrete classes implement appropriate interfaces (ports).
- [ ] Factories expose `build()` and avoid `static` product creation.
- [ ] Context and artifacts remain immutable.
- [ ] Rewrites are idempotent (no self-rewrap, no duplicate const, SemConv merge safe).
- [ ] Original business exceptions are preserved.
- [ ] External effects use ports.
- [ ] Sensitive data is classified and redacted.
- [ ] Buffers, payloads, and cardinality are bounded.
- [ ] Relevant tests were added or updated (Codeception).
- [ ] Architecture (PHPArkitect) and static-analysis (PHPStan) checks pass where available.
- [ ] Documentation reflects public behavior.
- [ ] No unrelated changes were introduced.
- [ ] Any unverified assumption is explicitly reported.
