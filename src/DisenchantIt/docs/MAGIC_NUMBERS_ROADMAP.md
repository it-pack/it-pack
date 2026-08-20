# MAGIC_NUMBERS_ROADMAP.md

> Concrete, ordered implementation steps for
> `docs/MAGIC_NUMBERS_PLAN.md`. Read that plan first. Each step lists the files
> to create/modify, the concrete action, the tests to add, and the quality gate
> to run before moving on. Keep every change incremental and green-gated.

Conventions used below:
- Paths are relative to the package root (`src/DisenchantIt/`).
- Quality gates (run from package root):
  - `composer test:unit`
  - `composer analyze` (PHPStan, src level 9)
  - `composer architecture` (PHPArkitect, scans `src` only)
  - `composer format:check` (PHP-CS-Fixer)
- Every concrete class is `final readonly` (or `final` with property hooks),
  implements a port, and is built via a factory whose product is created by an
  instance `build()` method (`create()` may proxy the constructor only).
- The source-text-safety hard rule applies everywhere: no `require`/`include`/
  `class_exists`/execution of analysed files.

---

## Progress

### Phase P1 — ✅ COMPLETE (2026-08-20)

All five P1 steps are implemented, tested, and green-gated. Summary of what
landed and the decisions taken:

- **Unified literal model.** `LiteralInterface` (in `ValueObject`, see
  deviation below) is the shared port for strings and numbers; `StringLiteral`
  and the new `NumberLiteral` both implement it. A `LiteralValueObjectTrait`
  supplies `needle()` (quoted form for strings, exact raw token for numbers).
- **Numeric detection with raw fidelity.** `MagicLiteralVisitor` (formerly
  `MagicStringVisitor`) now captures `LNumber`/`DNumber`, recording the EXACT
  source token via file-offset slice (`0x1F`, `1_000_000`, `3.14`), and
  excludes a numeric constant's own declaration value (no self-rewrap).
- **Pipeline widened to `LiteralInterface`.** `Detector::scanSource()` returns
  `list<LiteralInterface>`; `CandidateFilter`, `CandidateGrouper`,
  `ConstantPlacementStrategyInterface`, `Candidate`/`ConstantPlacement` and the
  three strategies now type against `LiteralInterface`. The filter keeps a
  `kind === string` guard so numbers are detected but not promoted yet (P2 lands
  naming).
- **Tests.** `NumberLiteralTest` + `MagicLiteralVisitorTest` added (6 tests):
  hex/separator/float raw fidelity, const-value exclusion, and
  detect-but-don't-promote. Full suite: **30 tests, 66 assertions, OK**.

**Deviations from this roadmap (intentional, documented):**
1. `LiteralInterface` lives in `src/ValueObject/`, **not** `src/Contract/` —
   PHPArkitect forbids `ValueObject → Contract` (one-directional rule,
   `phparkitect.php:41`); the existing `StringLiteralInterface` already sits in
   `ValueObject`, so the unified port belongs there. `Contract` ports may depend
   on it. (Updated in Step 1.2 above.)
2. The `MagicStringVisitor` class was **removed** rather than kept as a thin
   subclass — it could not extend the now-`final` `MagicLiteralVisitor`, and the
   only reference (`MagicStringVisitorFactory`) was repointed to build
   `MagicLiteralVisitor`. `MagicStringVisitorInterface` is retained as a
   pre-1.0 BC marker subtype of `MagicLiteralVisitorInterface`.
3. `RoleClassifierInterface::classify()` widened from `String_ $node` to
   `Node $node`; numbers are classified `'numeric_expression'` (excluded by the
   filter until P2).

**Gate status:**
- `composer test:unit` — ✅ 30 tests, 66 assertions.
- `composer analyze` (src only) — ✅ clean.
- `composer architecture` — ✅ no violations.
- `composer format:check` — ✅ clean.
- `composer analyze` (src **+ tests**) — ⚠️ errors remain, **all in
  `tests/Unit/*`** (now including the two new P2 test files). Cause: the
  `phpstan/phpunit` extension is not installed in this environment, so PHPStan
  cannot resolve PHPUnit/Codeception assertion methods and flags Codeception's
  `Unit` base as "contains abstract". This is pre-existing/environmental and
  unrelated to P2. Source-only analysis is clean.

---

### Phase P2 — ✅ COMPLETE (2026-08-20)

Naming, filtering and grouping for numbers are implemented and green-gated.
Numbers are now promoted only when a naming hint is discovered, and are named
from that hint.

- **Naming hints (`NamingContext`).** `ValueObject/NamingContext.php` (final,
  implementing `NamingContextInterface`) carries `variableName`, `comment`,
  `unit` and a `hasHint()`/`signature()` pair. The context is attached to a
  `NumberLiteral` at scan time by `NamingContextExtractor`
  (`src/NamingContextExtractor.php` + `Contract/NamingContextExtractorInterface`)
  which walks the literal's AST parents (assignment / array-key / property /
  param) for a name and a unit suffix, and scans the source line(s) above for a
  `//`/`#` comment. `LiteralInterface` gained a `?NamingContextInterface
  $namingContext` hook; `StringLiteral`/`NumberLiteral` carry it (null for
  strings).
- **Numeric name generation (`NumericNameGenerator`).** `src/NumericNameGenerator.php`
  + `Contract/NumericNameGeneratorInterface` derive `UPPER_SNAKE_CASE` names by
  the §4 priority: `unit suffix` (`$timeoutMs` → `TIMEOUT_MS`) → `comment`
  (`// seconds` above `$t = 30` → `T_SECONDS`) → `variable/array-key name`
  (`['timeout' => 30]` → `TIMEOUT`) → else `null` (not promoted).
- **Kind-aware filter.** `CandidateFilter` now branches on `LiteralKind`:
  strings keep the original rules; numbers are kept only when `hasHint()` is
  true AND the value is not trivial (`0`, `1`, `0.0`, `1.0`). Bare numbers in
  comparisons/branches carry no hint and stay un-promoted.
- **Kind-aware grouping.** New `Contract/GroupingStrategyInterface` with
  `ByValueGroupingStrategy` (strings by value) and
  `ByValueAndContextGroupingStrategy` (numbers by `value|ctx-signature`, kind
  prefixed `i:`/`f:`). `CandidateGrouper` dispatches by kind, so equal values
  with different hints (e.g. two `100`s named from `$timeoutMs` vs
  `$durationMs`) stay in separate promotion groups.
- **End-to-end naming (folded in from P3.4).** `Detector::detect()` and the
  `SameClass`/`PerClass` placement strategies now dispatch to
  `NumericNameGenerator` for numbers (wired through `Disenchanter` +
  `DisenchanterFactory` + `ConstantPlacementStrategyFactory`). P3.4's remaining
  work is the *typed* constant declaration (`int`/`float`) and SemConv
  type-awareness — not naming.
- **Tests.** 13 new tests (6 `NumericNameGeneratorTest`, 7
  `NumericDetectionTest`): unit-suffix/comment/array-key naming, `hasHint`
  presence, trivial-value exclusion, `TIMEOUT_MS`/`T_SECONDS` candidates, and
  two-equal-numbers-separate-groups. Full suite: **43 tests, 91 assertions,
  OK**.

**Deviations from this roadmap (intentional, documented):**
1. `NamingContextExtractorInterface::extract()` is `extract(Node $node, int
   $line, string $code): NamingContextInterface` — not `extract(LiteralInterface
   $lit, Node $node)`. The visitor has the `Node` + source at scan time; the
   source line/code are needed for comment extraction (pure AST comment
   attributes alone were unreliable). Returns the *interface* type so
   `Contract` never depends on a concrete value object (PHPArkitect).
2. `NamingContext` is backed by a new `NamingContextInterface` (a final class
   must implement a port per `phparkitect.php`). `LiteralInterface.namingContext`
   is typed `?NamingContextInterface` and `NumericNameGeneratorInterface`
   is typed against `LiteralInterface` + `?NamingContextInterface` (not the
   concrete `NumberLiteral`/`NamingContext`) for the same architectural reason.
3. `detect()` keys **numbers** with a kind prefix (`i:`/`f:`) + value + context
   signature (the roadmap's `'i:3'`, `'f:3.14'`), but **string** keys stay the
   raw value to preserve the existing `assertArrayHasKey('admin-user')`
   behavior (no BC break). The `'3'`/`3` collapse can't occur because every
   group key is already a string.
4. Numeric naming was wired into `detect()` + placement in P2 rather than
   deferred to P3.4, so the feature is coherent end-to-end. P3.4 is now scoped
   to typed const output + SemConv type-awareness only.
5. `hasHint()` is true for `unit || comment || variableName` (not only
   unit/comment/config-key). A bare assignment like `$x = 3` therefore *is*
   promotable (named `X`); only literals with no assignment/array-key/property
   context (e.g. `if ($x === 3)`) are filtered. This matches the roadmap's
   "no name ⇒ not promoted" intent.

**Gate status:**
- `composer test:unit` — ✅ 43 tests, 91 assertions.
- `composer analyze` (src only) — ✅ clean.
- `composer architecture` — ✅ no violations.
- `composer format:check` — ✅ clean.
- `composer analyze` (src **+ tests**) — ⚠️ same environmental phpstan/phpunit
  (Codeception `Unit`) limitation as P1; the 2 new P2 test files are subject to
  it. Source-only analysis is clean.

---

## Phase P1 — Unified literal model + numeric detection

Goal: capture numbers with exact raw tokens; no naming/promotion yet.

> **Status: complete (2026-08-20).** See Progress section above.

### Step 1.1 — `LiteralKind` enum
- **Create** `src/ValueObject/LiteralKind.php`:
  ```php
  namespace DisenchantIt\ValueObject;
  enum LiteralKind: string
  {
      case String = 'string';
      case Int = 'int';
      case Float = 'float';
  }
  ```
- Add a unit test asserting the three cases and their `value`.

### Step 1.2 — `LiteralInterface` + shared trait
- **Create** `src/ValueObject/LiteralInterface.php` (in `ValueObject`, NOT `Contract` —
  PHPArkitect's one-directional rule forbids `ValueObject → Contract`, and the
  existing `StringLiteralInterface` already lives in `ValueObject`, so the
  unified literal port belongs there too; `Contract` ports may depend on it):
  - `public string $kind { get; }`
  - `public string $value { get; }` (parsed scalar as string)
  - `public string $raw { get; }` (EXACT source token)
  - `public int $line { get; }`
  - `public string $role { get; }`
  - `public string $className { get; }`
  - `public string $methodName { get; }`
  - `public function needle(): string;`
- **Modify** `src/Contract/StringLiteralInterface.php`: make it
  `extends LiteralInterface` (additive; keeps existing contract).
- **Create** `src/ValueObject/LiteralValueObjectTrait.php`: stateless trait holding
  the shared fields (`kind`, `value`, `raw`, `line`, `role`, `className`,
  `methodName`) and a `needle()` helper (string → `var_export($value, true)`;
  default for numbers → `raw`). Limit trait to fields + helpers only (no state).
- **Modify** `src/ValueObject/StringLiteral.php`: `implements StringLiteralInterface`,
  use the trait; keep property hooks intact. Verify `kind` returns
  `LiteralKind::String->value`.

### Step 1.3 — `NumberLiteral` value object
- **Create** `src/ValueObject/NumberLiteral.php`:
  - `final readonly class NumberLiteral implements LiteralInterface`
  - Constructor: `LiteralKind $kind`, `string $value`, `string $raw`, `int $line`,
    `string $role`, `string $className`, `string $methodName`.
  - `needle()` returns `raw` (verbatim match in the source line).
- Unit test: `needle()` of `1_000_000` and `0x1F` equals the raw token.

### Step 1.4 — Capture numbers in the visitor
- **Rename** `src/MagicStringVisitor.php` → `src/MagicLiteralVisitor.php` (keep a
  `class_alias` or thin `MagicStringVisitor extends MagicLiteralVisitor` for BC in
  this pre-1.0 package). Extend `enterNode()` to also handle:
  - `Node\Scalar\LNumber` → `LiteralKind::Int`
  - `Node\Scalar\DNumber` → `LiteralKind::Float`
  - Record `raw` from `$node->getStartFilePos()` … `getEndFilePos()` slices of the
    original `$code` retained in the visitor.
  - Keep the existing `ClassConst` child-skip (so a const's own value is not a
    usage site).
- **Create** `src/Contract/StringLiteralDetectorInterface.php` widened sibling
  `src/Contract/LiteralDetectorInterface.php` whose `scanSource(): list<LiteralInterface>`.
  Keep `StringLiteralDetectorInterface` extending it (return type widens).
- **Modify** `src/Detector.php` `scanSource()` to return `list<LiteralInterface>`
  and build `NumberLiteral`s via the visitor.

### Step 1.5 — P1 tests + gate
- Tests (Codeception unit):
  - Raw fidelity: `'const X = 0x1F;'` → literal `raw === '0x1F'`, value `'31'`.
  - `'const Y = 1_000_000;'` → `raw === '1_000_000'`.
  - `'const Z = 3.14;'` → `kind === float`, `raw === '3.14'`.
  - Numeric `class_const_value` excluded by `RoleClassifier` (no self-rewrap).
- Run: `composer test:unit && composer analyze && composer architecture && composer format:check`.

---

## Phase P2 — Naming, filtering, grouping for numbers

Goal: only promote numbers we can name; group by value + naming context.

> **Status: complete (2026-08-20).** See Progress section above.

### Step 2.1 — `NamingContext` + extractor
- **Create** `src/ValueObject/NamingContext.php`: `final readonly` with fields
  `?string $variableName`, `?string $comment`, `?string $unit`, plus a property-
  hook computed helper `hasHint(): bool` (true if any field set).
- **Create** `src/Contract/NamingContextExtractorInterface.php`:
  `extract(LiteralInterface $lit, Node $node): NamingContext`.
- **Create** `src/NamingContextExtractor.php` (final, implements the port):
  - Walk parent nodes for an assigned variable / parameter / property whose name
    carries a unit suffix (`Ms`, `Seconds`, `Bytes`, `Ttl`, …) → set `$unit` +
    `$variableName`.
  - Capture a preceding `//` or `#` comment / docblock text → `$comment`.
  - Pure AST read only.

### Step 2.2 — `NumericNameGenerator`
- **Create** `src/Contract/NumericNameGeneratorInterface.php`:
  `generate(NumberLiteral $lit, NamingContext $ctx): ?string`.
- **Create** `src/NumericNameGenerator.php` (final) with the derivation priority
  from the plan §4: unit suffix → comment → config/array-key → else `null`.
- **Create** `src/Factory/ConstantNameGeneratorFactory.php` (final readonly
  implementing `ConstantNameGeneratorFactoryInterface`): `build()` assembles the
  existing `ConstantNameGenerator` + `NumericNameGenerator` + `NamingContextExtractor`.

### Step 2.3 — Kind-aware filter + grouping
- **Modify** `src/CandidateFilter.php`: add a `LiteralKind` branch. Numbers: drop
  `numeric_expression`, `numeric_negated`, trivial values (`0`, `1`, `0.0`,
  `1.0`), and any literal whose `NamingContext::hasHint()` is false (no name ⇒
  not promoted). Strings keep existing rules (`exception_message`,
  `format_string`, `enum_backing_value`, `class_const_value`, identifier-shape).
- **Create** `src/Contract/GroupingStrategyInterface.php` and two final impls:
  - `ByValueGroupingStrategy` (strings: key by value).
  - `ByValueAndContextGroupingStrategy` (numbers: key by `value|ctx-signature`).
- **Modify** `src/CandidateGrouper.php` to dispatch by `kind`.
- **Modify** `src/Detector.php` `detect()` key to a kind-prefixed composite
  (`'i:3'`, `'f:3.14'`, `'s:admin-user'`) to avoid PHP `'3'`/`3` key collapse.

### Step 2.4 — P2 tests + gate
- Tests:
  - `'$timeoutMs = 30000;'` → `TIMEOUT_MS` generated.
  - `'// seconds' . "\n" . '$t = 30;'` → `T_SECONDS` (or `TIMEOUT_SECONDS`).
  - Nameless `'if ($x === 3) {}'` → filtered out (no promotion).
  - Two `100`s with different variable hints stay in separate groups.
  - `0`, `1`, `0.0` excluded by default.
- Run all four gates.

---

## Phase P3 — Typed, raw-aware placement / rewrite / SemConv

Goal: emit `const int/float` with the original token; SemConv type-aware.

### Step 3.1 — Typed `ConstantPlacement`
- **Modify** `src/ValueObject/ConstantPlacement.php`: add `LiteralKind $type`
  (property-hook `declaredType` returning `int`/`float`/`string`), populated
  from the literal's `kind`. Keep `className`, `constName`, `value`, `line`.
- **Modify** `src/Contract/ConstantPlacementStrategyInterface.php` and the three
  strategies (`SameClassConstantStrategy`, `PerClassConstantStrategy`,
  `AutoConstantPlacementStrategy`) to set `type` from `LiteralKind`.

### Step 3.2 — Raw/typed rewriter
- **Modify** `src/LineBasedRewriter.php`: declaration template becomes
  `private const {type} %s = %s;` using `raw` for the value (so
  `private const int MASK = 0x1F;`). The existing-constant regex already tolerates
  a leading type — confirm via test. Self-rewrap + `class_const_value` guards
  already cover numeric consts.

### Step 3.3 — Type-aware SemConv
- **Modify** `src/SemConvCollector.php` `render()`: const type dynamic from
  `LiteralKind`; store **canonical** value (`var_export` of parsed scalar) as the
  registry key (so `0x1F` and `31` share an entry), class declaration keeps `raw`.
- **Modify** `src/SemConvAliasRewriter.php`: make its value comparison
  kind-aware (compare canonical value, not raw).

### Step 3.4 — Wire numeric name dispatch in `Disenchanter`
- **Modify** `src/Disenchanter.php`: after grouping, dispatch to
  `ConstantNameGenerator` (strings) vs `NumericNameGenerator` (numbers) by `kind`;
  `rewrite()` / `rewriteWithSemConv()` signatures unchanged.
- **Modify** `src/Factory/DisenchanterFactory.php`: inject
  `ConstantNameGeneratorFactory` product; thread numeric generator + extractor.

### Step 3.5 — P3 tests + gate
- Tests:
  - `'$mask = 0x1F;'` → `private const int MASK = 0x1F;`.
  - SemConv: `public const int MASK = 31;` in the registry; class aliases
    `SemConv::MASK`; `0x1F` and `31` treated as equal.
  - Idempotent re-run leaves file unchanged (no duplicate const, no self-rewrap).
  - Numeric and string consts coexist in the same class.
- Run all four gates.

---

## Phase P4 — Core refactor for PHP 8.4 performance

Goal: parse once per run; defer expensive parser construction; stream large trees.

### Step 4.1 — `SourceCode` value object (cache key)
- **Create** `src/ValueObject/SourceCode.php`: `final readonly` holding the raw
  `string $code` and an immutable hash/identity (property hook `identity(): string`).
- Thread a single `SourceCode` instance through `Disenchanter`
  detect→rewrite→SemConv (one per public call).

### Step 4.2 — `WeakMap` scan cache
- **Create** `src/ParsedSourceCache.php` (final): a `WeakMap` keyed by
  `SourceCode` → parsed AST (or scan result). `Detector` consults it before
  parsing. Non-owning: entries GC with the `SourceCode`.
- **Modify** `src/Detector.php` to accept `ParsedSourceCache` and the
  `SourceCode`; build `MagicLiteralVisitor` once and reuse.

### Step 4.3 — Lazy parser stack
- **Modify** `src/Factory/DetectorFactory.php` (final readonly): build the
  nikic parser + traverser + visitor stack inside a `Lazy` proxy so materialization
  happens only on first `scanSource`. Confirm via a test that the proxy is
  uninitialized before first scan.

### Step 4.4 — Generator streaming (optional)
- **Add** a `scanSourceStreaming(): \Generator` variant on
  `LiteralDetectorInterface` (default port keeps the array form). `MagicLiteralVisitor`
  yields literals instead of buffering when used in streaming mode. Bounds memory
  for very large trees.

### Step 4.5 — P4 tests + benchmark + gate
- Tests:
  - Same `SourceCode` across detect/rewrite/SemConv ⇒ nikic parse invoked exactly
    once (count parser construction / cache hits).
  - Lazy proxy not materialized until first `scanSource`.
  - Streaming generator yields every literal without building the full array.
- **Benchmark**: add a small script/example that scans a large fixture and measure
  scan throughput before/after; record numbers (no claimed improvement without
  measurement, per `AGENTS.md`).
- Run all four gates.

---

## Cross-cutting steps

### Step C.1 — Documentation
- Update `docs/USER_GUIDE.md` with a "Magic numbers" section (detection rules,
  naming-hint requirements, typed const output, idempotency).
- Add an example script `examples/12_magic_numbers.php` exercising
  `detect()`/`rewrite()` on numeric literals with naming hints; verify by running
  it. Add `examples/13_magic_numbers_semconv.php` for the SemConv path.
- Record this work as an ADR in `docs/REFACTORING_PLAN.md` (aggregate boundary,
  dependency direction, public contract widening, composite-key BC note).

### Step C.2 — Final verification
- `composer test:unit && composer analyze && composer architecture && composer format:check`
- Manual: run the two new example scripts and `php -l` the rewritten output in a
  throwaway copy (verification is a separate process, never inside DisenchantIt).

---

## Ordering summary

```
P1: kind enum → LiteralInterface+trait → NumberLiteral → visitor/detector (raw)
  → tests/gates
P2: NamingContext+extractor → NumericNameGenerator → filter/group (kind-aware)
  → tests/gates
P3: typed ConstantPlacement → raw/typed rewriter → SemConv type-aware →
    Disenchanter dispatch → tests/gates
P4: SourceCode VO → WeakMap cache → lazy parser → generators → tests+benchmark
C : docs + examples + ADR + final gates
```

Do not start P2 before P1 is green, P3 before P2 is green, P4 before P3 is green.
Each step is independently revertible.
