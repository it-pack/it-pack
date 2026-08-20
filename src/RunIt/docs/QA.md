# Quality Assurance & Static Analysis

This document describes the static-analysis gate enforced for `it-pack/run-it`
and the conventions behind it. It is the authoritative reference for the
Milestone M13 ("Conformance Tooling and QA Completion") static-analysis work.

## Gate overview

`composer analyse` runs PHPStan at the highest configured level (`max`) with:

1. **PHPStan core, level `max`** — the project's baseline.
2. **`phpstan/strict-rules` bundle** — auto-registered via
   `phpstan/extension-installer`. Enforces, among others: no loose (`==`)
   comparisons, no `empty()`, no short ternary, strict `in_array`, boolean-in-
   condition checks, no variable variables.
3. **RunIt-authored rules** — `tools/phpstan/runit-rules`, a small PHPStan
   extension implementing the project-specific rules below. No external
   rule packages were added; the rules live in the repository.

`composer quality` additionally runs `composer validate`, `php-cs-fixer`,
DepTrac, the test suites, and the security audit, so the static-analysis gate
is one component of a broader quality gate.

## RunIt-authored rules

| Rule | Enforces | AGENTS.md basis |
| --- | --- | --- |
| `forbidden-mixed` | No *implicit* (undeclared) parameter/return/property types. | "Avoid implicit `mixed`." |
| `forbidden-unsafe-types` | No `resource` type in signatures/properties. | `resource` cannot be serialized/observed safely; the other documented untyped types (`array`, `string`, `int`, `float`, `bool`, `object`, `callable`, `iterable`) remain permitted. |
| `no-var` | No `var` keyword; visibility must be explicit. | General PHP style. |
| `no-explicit-strict-types` | Every library file declares `declare(strict_types=1)`. | AGENTS.md: every file begins with `declare(strict_types=1)`. |
| `explicit-foreach` | No by-reference (`as &$v`) `foreach` binding. | Performance/determinism: avoid silent aliasing. |
| `forbid-reflection-on-hot-path` | No `Reflection*` usage in `Domain`, `Application\Runner`, `Application\Engine`. | AGENTS.md performance requirements (no reflection on hot paths). |
| `require-final` | Every concrete class is `final`. | "Every concrete class must be `final`." |
| `no-mutable-static` | Static properties must be `readonly`; no assignments to static properties. | Prohibits global mutable state. |
| `forbid-arbitrary-object-serialization` | No `serialize()` / `unserialize()` of objects. | "Arbitrary PHP object serialization is prohibited." |
| `forbid-direct-wall-clock` | No `time()` / `date()` / `new DateTime` / … outside `Adapter` (plus a small allowlist). | Time comes from the `MonotonicClockInterface` port. |
| `forbid-domain-imports-adapters` | Core layers must not `use` adapter implementations. | Hexagonal boundary (also enforced by DepTrac in M13.3). |
| `no-empty-catch` | No empty `catch` blocks (swallowed exceptions). | ADR-008: preserve the original failure. |
| `require-initialized-properties` | Properties are `readonly`, defaulted, or assigned in the class. | src-scoped equivalent of `checkUninitializedProperties`. |
| `require-readonly-artifact` | Domain artifacts (`Execution`, `Activity`, `Measurement`, `Observation`, `Occurrence`, `Snapshot`) are `readonly`. | ADR-002: artifacts are immutable. |
| `require-interface` | Every concrete class implements an interface (with a documented exemption list). | "Every concrete class must implement an appropriate interface." |

### Key decision: explicit `mixed` is permitted

AGENTS.md distinguishes between *implicit* `mixed` (a missing type hint) and
*explicit* `mixed`. It forbids the former and permits the latter as the escape
hatch for genuinely opaque or polymorphic boundaries — serializers, context/
occurrence/snapshot payloads, recorders, middleware, and polymorphic
validators. The `forbidden-mixed` rule therefore targets **implicit** types
only; explicit `mixed` remains a deliberate, reviewed choice at those
boundaries. This is also why these rules do not force breaking changes to public
contract signatures.

### `forbidden-unsafe-types` scope

Only `resource` is forbidden. The other untyped vocabulary types listed in
AGENTS.md (`array`, `string`, `int`, `float`, `bool`, `object`, `callable`,
`iterable`) are intentional and remain allowed.

### Import hygiene

`no-missing-imports` and `no-unused-imports` are enforced by `php-cs-fixer`
(`php-cs-fixer` rules `no_unused_imports` / `ordered_imports`), surfaced through
`composer cs:check`, not through PHPStan.

### Uninitialized properties (src-scoped rule)

PHPStan's `checkUninitializedProperties` toggle applies globally. On this
codebase it flags ~29 PHPUnit test classes whose properties are populated in
`setUp()` rather than the constructor — a normal, desirable PHPUnit pattern.
Rather than relax the check or plaster the test tree with property defaults, the
same intent is enforced for the **library** with the src-scoped
`require-initialized-properties` rule: a non-static, typed, non-`readonly`
property without a default value must be assigned via `$this->property = …`
somewhere in the class (typically the constructor). Promoted constructor
properties are already initialised and are not reported.

### Generic / iterable type checks

The ROADMAP lists `checkMissingIterableValueType` and
`checkGenericClassInNonGenericObjectType`. Both options were **removed in
PHPStan 2.x** (confirmed in `UPGRADING.md`) and are therefore unavailable. In
addition, `checkMissingIterableValueType` would directly conflict with AGENTS.md,
which explicitly permits untyped `array` ("Use `array` for untyped arrays").
Enabling a blanket missing-generic check would either force lossy union types
onto public contracts or contradict the coding standard. These two checks are
therefore intentionally **not enabled**; the library's own collection value
objects (`AttributeCollection`, `ExecutionContext`, …) already expose typed
projection methods, so the practical gap is the unsound use of raw generics,
which is out of scope for M13.2.

### Wall-clock allowlist

`forbid-direct-wall-clock` allows `src/Adapter` (where ports are implemented)
and two specific library sites:

- `src/Domain/Shared/Instant.php` — the `Instant::now()` factory, the single
  sanctioned place a domain value object reads the system clock.
- `src/Application/Publication/BufferedArtifactPublisher.php` — the default
  flush-clock closure (`time()`), a buffering default that predates port
  injection.

Both are tracked debt: `Instant::now()` should accept the `MonotonicClockInterface`
and the buffer should receive an injected clock. They are allowlisted so the rule
stays meaningful for every other layer.

### Interface-backing exemptions

`require-interface` enforces AGENTS.md's "every concrete class implements an
appropriate interface". A bounded set of existing classes is exempted because
extracting a semantic interface for them is a separate, tracked follow-up (and
AGENTS.md warns against creating interfaces "without semantic value"):

- Whole directories: `src/Conformance` (test-harness verifiers), `src/Behavior`
  (shared trait/behaviour helpers).
- Value-object / config / adapter-DTO classes: `SecurityLimits`,
  `ExecutedAs`, the publication configs (`ArtifactRedactionConfig`,
  `BufferConfiguration`, `PublicationPipeline`, `PublicationRoute`),
  `SnapshotIntegrityVerifier`, and the CloudEvents / OpenTelemetry / PSR adapter
  DTOs, builders, maps, and logger wrappers listed in `extension.neon`.

New service/port classes in the un-exempted tree are still required to implement
an interface.

### Exception checks

"Configure exception checks" is implemented as `no-empty-catch`: an empty
`catch` block silently swallows the failure, violating ADR-008 (preserve the
original business exception). Broad `catch (\Throwable)` blocks *are* permitted
in the Application layer — fail-safe publication, activity/execution lifecycle,
and health reporting intentionally catch and rethrow or route the original
failure; the rule only forbids the empty/swallowing form.

### Bleeding-edge analysis (non-blocking)

`composer analyse:bleeding-edge` runs the same configuration with
`vendor/phpstan/phpstan/conf/bleedingEdge.neon` included. It is an
informational job: it surfaces next-version PHPStan findings without gating
`composer quality`.

## Extending the rules

The extension lives at `tools/phpstan/runit-rules`:

- `extension.neon` — service registration and hot-path namespace list.
- `src/Rule/*` — one class per rule.
- `src/Support/*` — shared helpers (`AbstractSrcRule` restricts rules to the
  `src/` tree; `TypeNode` inspects declared type nodes).

Rules are restricted to the library source tree so the test suite and examples
are not subject to library-internal discipline. Add a new rule by implementing
`PHPStan\Rules\Rule` and registering it in `extension.neon`.
