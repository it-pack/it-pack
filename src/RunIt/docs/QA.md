# Quality Assurance & Static Analysis

This document describes the static-analysis gate enforced for `it-pack/run-it`
and the conventions behind it. It is the authoritative reference for the
Milestone M13 ("Conformance Tooling and QA Completion") static-analysis work.

## Gate overview

### Statement coverage (Phase 0, T0.4 of the 2026-08-25 plan)

`composer coverage` measures the Unit suite only. The former combined-suite
95.24% figure from 2026-08-25 is historical and does not establish Unit-only
coverage or per-test attribution. The refactoring plan records more recent
measurements, limitations and the target CI gate.

Declare the actual subject of a Unit test with PHPUnit coverage attributes;
use `UsesClass` or equivalent metadata for justified real collaborators rather
than crediting them as subjects. Use `CoversNothing` only when the test should
credit no production code, not merely because it exercises several classes or
checks properties. Named data sets can make shared invariants independently
identifiable; preserve setup and cases when converting to `DataProvider`. The
installed Codeception Unit loader recognizes `Codeception\Attribute\DataProvider`;
verify dataset discovery when using another runner or changing runner versions.

Coverage attributes express intent and affect attribution, but they do not by
themselves prove isolation. The installed Codeception strict-coverage negative
control did not fail for an undeclared class. See `REFACTORING_PLAN.md` for the
investigation and remaining enforcement work; do not treat a passing coverage
command as proof that unintended coverage is rejected.

The isolated diagnostic fixture in `tools/coverage-attribution/` makes this
behavior reproducible without writing to the package coverage report. From that
directory, run each Codeception control separately with PCOV enabled:

```bash
php -d pcov.enabled=1 -d pcov.directory=. ../../vendor/bin/codecept run Unit --filter testUndeclaredExecution --coverage --coverage-xml
php -d pcov.enabled=1 -d pcov.directory=. ../../vendor/bin/codecept run Unit --filter testTarget --coverage --coverage-xml
php -d pcov.enabled=1 -d pcov.directory=. ../../vendor/bin/codecept run Unit --filter testDeclaredCollaborator --coverage --coverage-xml
php -d pcov.enabled=1 -d pcov.directory=. ../../vendor/bin/codecept run Unit --filter testMissingMetadata --coverage --coverage-xml
php -d pcov.enabled=1 -d pcov.directory=. direct.php leak
php -d pcov.enabled=1 -d pcov.directory=. direct.php allowed
```

The first command currently exits 0 despite undeclared execution; inspect the
fixture report under `/tmp/kilo/runit-attribution-output/coverage.xml`. The
direct leak control exits 1 and the declared-collaborator control exits 0.
The fixture requires PCOV and installed development dependencies. It is not a
CI gate and does not demonstrate isolation for the real Unit suite.

The standalone preparatory threshold control accepts an explicit Clover report:

```bash
php tests/target-policy-coverage-regressions.php
php tests/target-policy-coverage.php tests/_output/coverage.xml
```

The first command runs synthetic boundary and invalid-input checks. The second
returns 0 at or above 95% statement coverage, 1 below it, and 2 for invalid or
missing input. Generate the report with `composer coverage` first; the checker
cannot verify its suite of origin or per-test attribution. It is not wired into
CI or the normal test suites and does not replace the planned isolation gate.

### Target-policy regressions

Run `php tests/target-policy-regressions.php` separately to check the approved
redaction, sampling, health-reporting and buffer byte-bound targets. It currently
exits nonzero for known violations and is intentionally excluded from the normal
Codeception suites; a passing normal suite does not mean those policies are
implemented. The checks use synthetic inputs and do not cover every exporter,
configuration, overflow edge case or failure path. See `REFACTORING_PLAN.md` for
the observed failures and remaining design gates.

### Suite-leakage rule (Phase 0, T0.5)

Suites mirror namespaces: Unit tests target Domain/Application/SDK/Behavior/
Adapter units in isolation; Integration exercises cross-layer wiring;
Conformance owns the verifier fixtures; Concurrency owns Fiber/coroutine
isolation. Shared fakes live in `tests/Support/Fixture`. A Unit test that
needs an Integration-only bootstrap or performs network/filesystem I/O is
misplaced — move it in the same PR that adds it.

`composer analyse` runs PHPStan at the highest configured level (`max`) with:

1. **PHPStan core, level `max`** — the project's baseline.
2. **`phpstan/strict-rules` bundle** — auto-registered via
   `phpstan/extension-installer`. Enforces, among others: no loose (`==`)
   comparisons, no `empty()`, no short ternary, strict `in_array`, boolean-in-
   condition checks, no variable variables.
3. **RunIt-authored rules** — `tools/phpstan/runit-rules`, a small PHPStan
   extension implementing the project-specific rules below. No external
   rule packages were added; the rules live in the repository.

`composer quality` runs strict Composer validation, Rector's dry run,
PHP-CS-Fixer's check, PHPStan, phparkitect, the line-length check, all four
configured test suites, the production-only security audit, and the full
dependency audit, in that order. Composer stops the sequence on failure. The
full audit also checks development dependencies. It reports abandoned packages
without failing solely because of abandonment; security advisories still fail
the audit. The production-only audit retains Composer's default abandonment
policy. Coverage, API compatibility, mutation tests, and benchmarks are separate
checks.

## Rector

`rector.php` configures Rector for `src`, `tests`, and `examples`, excluding
`tests/Support/_generated`. Run `composer refactor:dry` to preview proposed
changes, or `composer refactor` to apply them; `vendor/bin/rector` is also
available directly. Run Rector before PHP-CS-Fixer because Rector may alter
formatting. Review automated changes for behavior, public API, and compatibility
before accepting them, and preserve unrelated work. The current configuration
enables PHP 8.5 sets while `composer.json` supports PHP 8.4; verify PHP 8.4
compatibility rather than assuming all Rector output is safe for the minimum.
In particular, review fixture methods and callable representations: removing an
apparently empty method can invalidate a callable, and converting callable arrays
to closures can remove coverage of array-specific behavior.

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
| `member-order` | Canonical class-member ordering: trait `use` → constants → properties → constructor → static methods → instance methods, with public before protected/private within each group. | Phase 1 T1.1 / AGENTS.md conventions. |
| `forbid-leading-backslash-reference` | Inlined leading-backslash FQCNs (written as `\Foo\Bar`) must be replaced by a `use` import; two Swoole probe files are exempt. | Phase 1 T1.2 / REVIEW R-1. |

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

### Interface-backing: seam-backed vs private-leaf

`require-interface` enforces AGENTS.md's "every concrete class implements an
appropriate interface". The rule is split into two lawful categories (ADR-015):

- **Seam-backed** — any class consumed across a boundary (another namespace, a
  port, a builder, or injected collaborators) MUST implement an interface. The
  rule enforces this directly. Examples promoted in Phase 3, T3.1:
  - PSR-3 loggers `ExecutionAwareLogger`, `ObservationRecordingLogger` declare
    `implements \Psr\Log\LoggerInterface`.
  - OpenTelemetry lookup maps `SeverityMap`, `SpanKindMap`, `SpanStatusMap`
    declare `implements SeverityMapInterface` / `SpanKindMapInterface` /
    `SpanStatusMapInterface`; the mappers that inject them depend on the
    contract, not the concrete map.
- **Private leaf** — `final` adapter-private value/config/lookup/constants/
  wrapper types, builders, attributes, and the safety-net reporter MAY omit an
  interface; they are recorded as permanent exemptions. Each entry exists because
  an interface would add no semantic value, not because a contract is pending.

Permanent private-leaf exemptions:

- Whole directories: `src/Conformance` (test-harness verifiers/fixtures),
  `src/Behavior` (shared trait/behaviour helpers).
- **PHP attributes**: `ExecutedAs` — attributes are metadata; a contract behind
  them has no consumer.
- **Internal last-resort sinks**: `FallbackErrorLogReporter` — the safety net
  that contains throwing health callbacks must never be swappable by
  configuration, so exposing a contract would only undermine it.
- **Builders of their own config type**: `CloudEventsBridgeConfigBuilder`,
  `BridgeConfigBuilder` — the built VO is the contract.
- **Pure data carriers / lookups inside their adapter or config namespace**:
  `CloudEvent`, `CloudEventsBridgeConfig`, `CloudEventMappingPolicy`,
  `BridgeConfig`, `BridgeCapability`, `InstrumentCache`, `AttributeKeys`,
  `OccurrenceEventWrapper`, `SecurityLimits`, `ArtifactRedactionConfig`,
  `BufferConfiguration`, `PublicationRoute`.

Stale entries were pruned in Phase 3, T3.1: `PublicationPipeline` and
`SnapshotIntegrityVerifier` implement interfaces today and
`HttpContextExtractorInterface` was listed although it *is* an interface.

New service/port classes in the un-exempted tree are still required to implement
an interface.

### Contract enums without interfaces (deliberate)

`NestedExecutionMode` and `ContextCollisionPolicy` are closed internal value
sets consumed directly (runner `match`, merge strategies); no polymorphic
consumer exists, so pairing them with marker interfaces would be speculative
abstraction (AGENTS.md: "Do not create interfaces solely to duplicate another
interface without semantic value"). Revisit if an adapter ever needs to supply
its own mode/collision implementations.

### Known safe `new static()` (Phase 2, T2.2)

`Snapshot::build()` uses `new static()` so the inherited `Snapshot::create()`
factory constructs the concrete subclass (`ExecutionSnapshot`). The
`new.static` strict rule flags every `new static()` in a non-final class; here
the usage is provably safe — `Snapshot` has exactly one subclass, which
inherits the now-protected constructor from parent scope, so late-static
binding can never hit an inaccessible or missing constructor. A single scoped
inline ignore with that rationale sits on the construction line. Do not copy
the pattern onto extension points with unknown subclass trees.

### Suite pipeline honesty (Phase 3, T3.4)

The default `composer test` pipeline runs only suites that contain tests:
`Unit`, `Integration`, `Conformance`, and `Concurrency`. The empty Functional
suite was removed from the default gate so a suite can never report silent
green; `test:functional` remains available for manual use and returns to the
pipeline when the suite gains content. The boilerplate Acceptance suite (and
its browser-emulation config) was deleted: RunIt is a library, not an
application under browser test.

`Adapter\Psr\Http\HttpExecutionContext` was previously a concrete `@api` DTO with
no interface and **not** on the exemption list — a genuine `runit.requireInterface`
violation that made `composer analyse` fail. Phase 0 (T0.1) closed this by adding
`Contract\Shared\HttpExecutionContextInterface` (accessor-based, since PHP
interfaces cannot declare properties) and having the DTO implement it while
keeping its public `readonly` properties. It now satisfies the rule and is no
longer part of the interface gap.

### `shipmonk.deadMethod` notices resolved (Phase 0, T0.1 of the 2026-08-23 plan)

`composer analyse` now reports **0 errors**. The previously-accepted set of 15
`shipmonk.deadMethod` notices was triaged in Phase 1 (T1.2) as deliberately-kept
public surfaces; they are now formally declared as such instead of remaining
gate noise:

- `SecurityLimits` carries a class-level `@api` tag — its fluent withers
  (`withMaxAttributeKeyBytes`, …, `withMaxSerializationBytes`) are supported
  public API for tuning limits through
  `RunItBuilder::withSecurityLimits()` (the seam added by T0.1).
- `PublicationPipelineInterface`, `VerifierInterface`,
  `SnapshotIntegrityVerifierInterface`, and `AttributeValidatorInterface`
  carry interface-level `@api` tags — their externally-consumed methods
  (`build`, `port`, `verifyIntegrity`, `verifyChain`, `assertValidKey`,
  `assertValidAttributes`) are extension points for consumers and third-party
  adapter authors.

The dead-code detector's `apiPhpDoc` usage provider treats `@api` members as
used, which matches their declared contract status exactly — no
`@phpstan-ignore`, no baseline, and no deletion of public API. The table below
is retained for history.

<details>
<summary>Formerly-accepted notices (resolved 2026-08-23)</summary>

| # | Symbol | File | Kind | Resolution |
|---|--------|------|------|------------|
| 1 | `PublicationPipelineInterface::build` | `src/Application/Publication/PublicationPipelineInterface.php:21` | Public port contract method | Interface marked `@api`. |
| 2 | `VerifierInterface::port` | `src/Conformance/Contract/VerifierInterface.php:23` | Conformance harness contract method | Interface marked `@api`. |
| 3 | `SnapshotIntegrityVerifierInterface::verifyIntegrity` | `src/Contract/Application/Security/SnapshotIntegrityVerifierInterface.php:25` | Public security contract method | Interface marked `@api`. |
| 4 | `SnapshotIntegrityVerifierInterface::verifyChain` | `src/Contract/Application/Security/SnapshotIntegrityVerifierInterface.php:30` | Public security contract method | Interface marked `@api`. |
| 5 | `AttributeValidatorInterface::assertValidKey` | `src/Contract/Domain/AttributeValidatorInterface.php:20` | Public domain contract method | Interface marked `@api`. |
| 6 | `AttributeValidatorInterface::assertValidAttributes` | `src/Contract/Domain/AttributeValidatorInterface.php:25` | Public domain contract method | Interface marked `@api`. |
| 7–15 | `SecurityLimits::withMax*` (9 withers) | `src/Domain/Shared/SecurityLimits.php:87–151` | Public fluent withers | Class marked `@api`; reachable via `RunItBuilder::withSecurityLimits()`. |

</details>

### Recorder `$clock` is intentionally used (Phase 3, T3.4)

The four recorders (`Default*Recorder`) declare `private MonotonicClockInterface $clock`
and were suspected of a `property.onlyWritten` false positive. **Confirmed not an issue:**
`InstantClockBehavior::now()` reads `$this->clock->nowSeconds()` and every recorder calls
`$this->now()` when timestamping artifacts, so `$clock` is a genuine, read dependency — not
dead. PHPStan does **not** currently flag `$clock` as only-written (its trait-usage analysis
resolves the read through the trait), so no `@phpstan-ignore` is needed and the dependency is
left in place. Do not "fix" this by removing `$clock`.

### Complexity guard (Phase 3, T3.2)

`runit.complexity` (rule `Tools\PhpStan\RunitRules\Rule\ComplexityGuardRule`, registered in
`tools/phpstan/runit-rules/extension.neon`) counts branch-bearing nodes (`if`/`elseif`/`else`,
`switch`/`case`, loops, `try`/`catch`, ternary, coalesce, `match` arms) per `src/` method as a
proxy for cognitive complexity. A method above the configured **threshold (19)** is flagged.

The threshold is tuned to the current worst offender (measured at 19), so the rule is a
**regression guard**: nothing in the tree is flagged today, but a newly-added method more
complex than the current maximum is caught before merge. The "no `else`/`switch`" convention
already keeps most methods small, so 19 is a permissive ceiling, not a target — lower it only
with a recorded reason here. No method exemptions are needed at threshold 19 (the wide
builders/runner are far below it).

### Final-class exemptions

`require-final` enforces AGENTS.md's "every concrete class must be `final`". One
class is exempted because it is the single, deliberately non-`final` base refined
by exactly one vocabulary-backed subtype:

- `src/Domain/Snapshot/Snapshot.php` — extended only by `ExecutionSnapshot`
  (`runit:ExecutionSnapshot` is a subclass of `runit:Snapshot` in the vocabulary).
  No further subtypes are permitted; this is the documented ADR-015 exception for
  a vocabulary-backed refinement. The exemption is listed in `extension.neon`.

All other concrete classes in `src` are required to be `final`; `readonly` is
additionally required for domain artifacts by `require-readonly-artifact`.

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

## Historical DepTrac layering (M13.3)

The following describes the former DepTrac setup, not the current gate.
`composer architecture` now runs phparkitect using `phparkitect.php`.
The former configuration enforced the hexagonal dependency direction from `AGENTS.md`
with the following layers: `Common`, `Contract`, `Port`, `Domain`, `Application`,
`SDK`, `Common Adapter`, `External Adapter`, `Test Support`.

Key boundaries:

- `Domain` may depend only on `Common`, `Contract`, `Port`. It cannot reach
  `Application`, `SDK`, or any adapter (prohibits domain-to-application and
  domain-to-adapter dependencies).
- `Application` may depend only on `Common`, `Contract`, `Port`, `Domain`. It
  cannot reach `SDK` or any adapter (prohibits application-to-SDK and
  application-to-external-adapter dependencies).
- `External Adapter` is reachable only from `Common Adapter`, the other adapters,
  and `Test Support`; the core layers never import it.

`Port` is a deliberately exclusive subset of `src/Contract/Port` (the `Contract`
collector uses `src/Contract/(?!Port/).*` so no token is assigned to two layers);
this keeps DepTrac warnings at zero. No `skip` rules are used, so "zero
unexplained skips" is met by having no skips.

The report is **0 violations / 0 skipped / 0 warnings**. DepTrac reports 134
uncovered dependencies; every one is an adapter class depending on a vendor
library (`OpenTelemetry*`, `Psr*`, `CloudEvents*`, Swoole/OpenSwoole `*`). These
are the external integration points permitted by ADR-006 and ADR-010, confined to
the adapter layers, and are reviewed and accepted rather than suppressed. The
approved architecture diagram is `docs/architecture/dependency-graph.md`,
generated from `deptrac.yaml` via `deptrac analyse --formatter=mermaidjs`.

## Coding style (M13.4)

`composer cs:check` runs `php-cs-fixer fix --dry-run --diff`; `composer cs:fix`
applies the same ruleset. The configuration (`.php-cs-fixer.php`) is finalized and
covers every M13.4 checklist item:

- **Base sets** — `@PSR12` (PSR-12 lineage) and `@PHP84Migration` (modern PHP 8.4
  syntax, e.g. `Type $x = null` instead of `?Type $x = null`).
- **Strict types** — `declare_strict_types` is enforced (mirrored by the
  `no-explicit-strict-types` PHPStan rule).
- **Import ordering** — `ordered_imports` plus `no_leading_import_slash` keep
  `use` statements sorted and slash-free.
- **Trailing commas** — `trailing_comma_in_multiline` with
  `no_trailing_comma_in_singleline` (multiline lists get trailing commas; single
  lines never do).
- **Modern literal style** — `array_syntax` (short), `single_quote`,
  `no_superfluous_elseif`, `no_useless_return`,
  `multiline_whitespace_before_semicolons` (chained calls on their own line).
- **Strict operators/calls** — `strict_comparison` and `strict_param`.

### Risky rules, reviewed individually

`setRiskyAllowed(true)` is required because the following rules are flagged risky
by PHP-CS-Fixer; each is reviewed and deliberately kept:

- `strict_comparison` / `strict_param` — safe because every file declares
  `declare(strict_types=1)`; strict comparison and strict parameter calling are
  the intended semantics everywhere, not an incidental coercion change.
- `no_unused_imports` — safe; class-string references and phpdoc types are
  explicit, and removing a genuinely unused import cannot alter runtime behavior.

No other risky rules are enabled, and no `:risky` migration set is used, so the
risky surface is bounded to the three rules above.

### Local commands and CI

- Check only, in order: `composer refactor:dry`, then `composer cs:check`.
- Apply changes, in order: `composer refactor`, review its diff, then
  `composer cs:fix` and review the resulting diff.

CI (`.github/workflows/quality.yml`) runs `composer cs:check` inside the
`quality` matrix (PHP 8.4/8.5 × locked/lowest) and also as a dedicated
`coding-style` job. Both are check-only; the workflow never modifies the tree.

### One-type-per-file rule scope

Every file under `src` declares exactly one type. In `tests`, a `*Test` class
must be the only declared type in its file (PSR-4 requirement — a test class
declared inside another test's file is autoloaded from the wrong path and is a
real breakage, e.g. the historical `HmacSnapshotSignerTest` case). The allowed
exception is **single-file helper types**: subject-coupled fixtures, spies, and
fixture classes may stay inline directly below their only consumer when moving
them out would obscure the test's subject or invite speculative reuse. Genuinely
shared doubles live in `tests/Support/Fixture` instead and must be extracted on
first reuse. As of the Phase 3 hygiene pass the inline allowance applies to:
`RunItBuilderTest` (builder-seam spies), `ExecutedCallableTest`,
`ExecutedAsMetadataReaderTest`, `FailSafeRecursionTest`,
`CompositeContextStorageTest` (storage-selection fakes), `DogfoodRunnerTest`
(`FakeProcessRunner`), and `CoroutineContextStorageTest`
(`FixedCoroutineIdProvider`). Anonymous classes are expressions, not declared
types, and are always acceptable.

### Line-length guard (≤120 columns)

`composer lint:length` (`tools/lint/line-length.php`) fails on any code line
longer than 120 columns in `src`, `tests`, **and `tools`** (vendored stubs under
`tools/**/vendor` are ignored); it runs as part of `composer quality`. Docblock/
comment lines are exempt, and Codeception's generated actor artifacts
(`tests/Support/_generated`) are ignored. The plan's preferred PHPStan `FileRule`
variant is not available against the phar-based PHPStan build, so the documented
`composer lint:length` alternative was chosen.

### Tooling scope (REVIEW R-5, R-6, R-13)

First-party developer tooling under `tools/` follows the same mechanical
disciplines as the library:

- **Line length** — `composer lint:length` scans `tools` (see above).
- **Guard clauses** — `ForbidElseRule` is registered for the `tools` root as well
  as `src`, so `else`/`elseif` are disallowed in first-party tooling; branches use
  early-return / `continue` / `match` instead.
- **No inlined leading-backslash FQCNs** — the `forbid-leading-backslash-reference`
  rule is registered for the `tests` root; tooling imports its dependencies.

The one sanctioned exception is `Tools\PhpStan\RunitRules\Support\AbstractSrcRule`,
which is intentionally `abstract`. It is a tooling base class (never shipped in the
library) and is exempt from the "every concrete class implements an interface" and
"no abstract classes" expectations; converting it to a trait buys nothing while only
one such base exists.

## Mutation testing (M13.5)

`composer mutation` runs Infection (PHP Mutation Testing Framework 0.33) against
the Unit suite; `composer mutation:changed` limits the run to git-changed lines.
The configuration (`infection.json5`) is finalized:

- **Source directories** — `src` (the library; tests are excluded from mutation).
- **Test framework integration** — `testFramework: codeception` via the
  `infection/codeception-adapter` dev dependency, scoped to the `Unit` suite
  (`testFrameworkExtraArgs: "Unit"`).
- **Per-mutant execution** — the `mutation` / `mutation:changed` scripts pass
  `--only-covering-test-cases` so each mutant reruns only its covering Unit
  tests instead of the full suite. This is essential: running the full Unit
  suite per mutant exceeds Infection's timeout and produces mass timeouts.
- **PHPStan integration** — intended as `staticAnalysisTool: phpstan` to kill
  escaped mutants that PHPStan can prove invalid (e.g. type errors), reducing
  false escapes without new tests. It is **currently disabled**. The refactoring
  plan records `composer analyse` passing at revision
  `133db4746e2c87c2e2ace6d803020412ce3d6ce8` and `composer quality` passing
  after subsequent work based on `c4841f341d8c6e8efbcc19ad6dfd85d6eb8aa205`.
  Infection integration remains disabled to keep local mutation runs stable and
  avoid coupling the mutation gate to PHPStan's per-mutant cost. PHPStan still
  runs as a separate `composer analyse` gate.
- **Coverage** — Infection auto-detects the `pcov` driver; the Unit suite is run
  once for initial coverage, then each mutant reruns only its covering tests.
- **Mutators** — `@default` profile (the standard, non-risky mutator set).
- **Changed-line testing** — `mutation:changed` uses `--git-diff-lines` so only
  added/modified lines are mutated (pre-push / pre-merge hygiene).
- **Logs** — text log to `var/infection.log` and a JSON summary to
  `var/infection.summary.json`.

### MSI floors (ratchet gate)

`minMsi` and `minCoveredMsi` are the mutation ratchet gate (REVIEW R-4 / plan Phase 4
T4.2). They start at `0` in both `infection.json5` (full profile) and
`infection.s1s6.json5` (critical-branching profile) so the first CI run of
`.github/workflows/mutation.yml` can capture the baseline without failing. Once the
CI baseline is recorded in the M13.5 status audit (`docs/ROADMAP.md`), replace them
with `(baseline − ~5 points)`. Infection fails the job only when a floor is breached;
timed-out mutants are excluded from MSI by default, so transient timeout noise never
fails the job. Local runs confirm covering-test selection works
(`--only-covering-test-cases`); the full profile remains CI-scoped because timeout
mutants make a whole-suite local pass slow.

### Blocker fixed to enable mutation testing

Infection drives Codeception under strict PSR-4 autoloading, which exposed a
latent PSR-4 violation: `Tests\Support\Fixture\Psr\FakeExecutionRunner` and four
`Noop*` recorder doubles were declared inside `FakeExecutionScope.php` instead of
their own files. Codeception's lenient support autoloader masked this, but
Infection's initial-test run failed with `Class not found`. The five classes were
extracted into their own PSR-4-compliant files
(`FakeExecutionRunner.php`, `NoopActivityRunner.php`, `NoopMeasurementRecorder.php`,
`NoopOccurrenceRecorder.php`, `NoopSnapshotRecorder.php`), which unblocked the
pipeline. The Unit suite remains green (813 tests / 829 across the shipped pipeline).

### Dogfood teardown abort fixed (REVIEW §3 P1-2, plan T1.1)

A second blocker prevented `composer mutation` from completing: Infection's
initial-test run aborted on `Tests\Unit\Tools\Dogfood\DogfoodRunnerTest` (the
`runit.dogfood.fail` scenario). The Codeception subprocess receives a SIGTERM
during teardown and `Codeception\Subscriber\GracefulTermination` converts it into a
bare `RuntimeException` ("TESTS EXECUTION TERMINATED"), which Infection attributes to
the in-flight test and aborts before any mutant runs. The dogfood tests exercise the
dev-tooling harness, not library mutants, so they are excluded from both Infection
profiles via `@group dogfood` + `testFrameworkExtraArgs: "Unit --skip-group dogfood"`
(see `infection.json5` / `infection.s1s6.json5`). After this change Infection processes
all source files and prints an MSI; the full-profile run is CI-scoped because timeout
mutants make a whole-suite local pass slow, so the baseline MSI is owned by CI.

### Reviewing escaped mutants

When `composer mutation` reports escaped mutants, triage them:

1. **Genuine gap** — add or strengthen a Unit test that asserts the killed
   behavior; this raises MSI and is the preferred outcome.
2. ** Equivalent / unreachable** — if the mutant is provably equivalent (e.g. a
   boundary already covered by construction) or exercises dead code, document it
   in the M13.5 status audit rather than forcing a test.

## API compatibility (M13.6)

`composer api:check` runs the reflection-based backward-compatibility checker in
`tools/api-compat/` against committed baselines (`tools/api-compat/api-baseline.json`
and `tools/api-compat/normalization-schema-baseline.json`). It detects removed
or narrowed symbols/methods/types, public interface method additions, enum case
changes, internal-namespace leaks, and normalization-schema changes (tracked
separately in `config/normalization-schema.json`).

- `composer api:baseline` — regenerate the baselines (run at each release
  candidate).
- The public namespace scope and excluded internal namespaces are configured in
  `tools/api-compat/config.json`.
- The full review process, detection list, and baseline policy are in
  `docs/compatibility.md`.

The checker is dependency-free (PHP reflection only) so it runs on every PHP
version in the support matrix; Roave Backward Compatibility Check was evaluated
but is incompatible with the PHP 8.5 / Symfony 8 matrix.

## Extending the rules

The extension lives at `tools/phpstan/runit-rules`:

- `extension.neon` — service registration and hot-path namespace list.
- `src/Rule/*` — one class per rule.
- `src/Support/*` — shared helpers (`AbstractSrcRule` restricts rules to the
  `src/` tree; `TypeNode` inspects declared type nodes).

Rules are restricted to the library source tree so the test suite and examples
are not subject to library-internal discipline. Add a new rule by implementing
`PHPStan\Rules\Rule` and registering it in `extension.neon`.
