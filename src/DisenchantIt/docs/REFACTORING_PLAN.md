# DisenchantIt — Refactoring Plan (revised)

> Companion to `README.md` and `docs/REVIEW.md`.
>
> This revision folds the **in-depth engineering review** (`docs/REVIEW.md`) into the
> original Phase-0 "polish" plan. The original plan (old §1–§17) was written *before*
> the P1–P4 implementation landed; this document **reconciles that status**, keeps the
> still-valid structure (factory contract, ADRs, execution model, verification gate),
> and converts every open finding — both the surviving original items and the new
> REVIEW findings — into **step-by-step implementation tasks**.
>
> Review lenses (unchanged): **SOLID**, **DRY**, **Ports & Adapters**, **DI via
> Factories** (§0), **PHP 8.4+ features**, **Traits**, **It_Pack Execution Model**.
>
> Severity convention: 🔴 strong candidate · 🟠 worthwhile · 🟡 minor/hygiene.
>
> Each finding lists: location, principle, severity, current behaviour, and a numbered
> **implementation procedure** with its own verification step.

---

## 0. Canonical Factory contract (applies to every finding below)

The user-specified factory rules, written once so every later finding can rely on them:

1. A factory is **instantiated first**; it never creates product instances through a
   `static` method. No `static function build()` / `static function make()`.
2. The product is created via an instance method named **`build()`**.
3. A factory MAY expose a `static function create(...): self` that **proxies its own
   constructor** — lets the composition root build a factory without `new`. `create()`
   returns the factory, never the product.
4. Factories are `final readonly class` implementing a `…FactoryInterface` port.

```php
interface ConstantPlacementFactoryInterface
{
    /** @param list<StringLiteral> $occurrences */
    public function build(
        string $className,
        string $constantName,
        string $value,
        array $occurrences,
    ): ConstantPlacement;
}

final readonly class ConstantPlacementFactory implements ConstantPlacementFactoryInterface
{
    public function build(
        string $className,
        string $constantName,
        string $value,
        array $occurrences,
    ): ConstantPlacement {
        return new ConstantPlacement($className, $constantName, $value, $occurrences);
    }

    /** Proxy constructor so callers avoid `new`. */
    public static function create(): self
    {
        return new self();
    }
}
```

Usage (DI, never inline `new`):

```php
final readonly class SameClassConstantStrategy implements ConstantPlacementStrategyInterface
{
    public function __construct(
        private ConstantPlacementFactoryInterface $placements,
        private ConstantNameGeneratorInterface $namer,
    ) {}

    public function decide(array $occurrences): array
    {
        if ($occurrences === []) {
            return [];
        }
        $value = $occurrences[0]->value;
        return [$this->placements->build(
            className: $occurrences[0]->className,
            constantName: $this->namer->generate($value, $occurrences[0]->role),
            value: $value,
            occurrences: $occurrences,
        )];
    }
}
```

---

## 1. Status reconciliation — what is already done

P1–P4 are implemented. The table maps the **original plan items** to the **current
state** so the remaining work is unambiguous.

| Original | Topic | State | Note |
|---|---|---|---|
| §0 | Factory contract | ✅ Done | `DisenchanterFactory`/`DetectorFactory`/… follow §0. |
| §1 | `Detector` DI (no inline `new`) | 🟡 Partial | Collaborators injected; parser built lazily + reused. **Namespace move to `Adapter\` not done** (classes still flat). |
| §2 | Split filter / grouper / namer | ✅ Done | `CandidateFilter`, `CandidateGrouper`, `ConstantNameGenerator` extracted. |
| §3 | Strategy + `ConstantPlacement` DI | ✅ Done | `ConstantPlacementFactory` injected into both strategies. |
| §4 | `SemConvConfig` factory | ✅ Done | `SemConvConfigFactory` + injected config. |
| §5 | Core orchestration | 🟡 Partial | `Disenchanter` Core exists; no CLI / `PhpFileFinder` port yet (library is single-string by design). |
| §6 | `RoleClassifier` / visitor ports | ✅ Done | `RoleClassifierInterface`, `MagicStringVisitorInterface` exist. |
| §7 | readonly / property hooks / enums | ✅ Done | `LiteralKind` enum done; `readonly` now consistent across stateless services (F5); property hooks only on ports; roles still bare strings; `SemConvConfig` hooks not done. |
| §8 | Lazy Objects for parser | 🟡 Partial | **Manual first-use lazy init** chosen over `Lazy`/`GhostObject` (acceptable; revisit optional). |
| §9 | WeakMap caching | ✅ Done | `ParsedSourceCache` now keys by `SourceCode::identity()` so one parse per distinct source across `detect`→`rewrite`→`rewriteWithSemConv` (F1). |
| §10 | File-discovery DRY | ⬜ Not started | N/A for library until a CLI/finder port lands. |
| §11 | Constant-rendering DRY | ⬜ Not started | `LineBasedRewriter` & `SemConvCollector` each `sprintf` their own `const` line. |
| §12 | `use`-insertion trait | ⬜ Not started | `SemConvAliasRewriter::ensureUseImport()` is self-contained. |
| §13 | Domain exceptions | 🟡 Partial | `SourceTooLargeException` added (F8) implementing `DisenchantItExceptionInterface`; `SemConvCollector` still throws a generic `RuntimeException` on collision (see §3.6). |
| §14 | OCP strategy registry | ⬜ Not started | `AutoConstantPlacementStrategy` still hard-codes the `<= 1` branch. |
| §15 | Hygiene (callables / return types) | 🟡 Partial | `match` used; some port return types still PHPDoc-only. |
| §16 | PHPArkitect + ADRs | 🟡 Partial | `phparkitect.php` present and **green**; **layered rules dormant** (no `Core`/`Adapter` namespaces yet); ADR *documents* not yet written. |
| §17 | Execution Model | ⬜ Not started | No `ExecutionRecorder` port / immutable Context-Occurrence VOs yet. |

The **new REVIEW findings** (below) were the highest-leverage remaining work: F1 and F2
were the two that actually changed behaviour/correctness; F3–F8 were consistency,
robustness, and honesty-of-docs fixes. **All of F1–F8 are now implemented and green**
(see §2.1). The remaining work is §3.1–§3.10.

---

## 2. REVIEW.md findings → implementation tasks

### F1 — Make parse-once / WeakMap actually fire  ·  ✅ Done  ·  REVIEW #1

- **Location:** `src/Disenchanter.php:54` (`new SourceCode($code)` on every call),
  `src/ParsedSourceCache.php:23` (WeakMap keyed by **object identity**),
  `src/ValueObject/SourceCode.php:19-22` (`identity()` content hash **unused**).
- **Principle:** Performance, DRY (avoid redundant work), honesty of stated P4 intent.
- **Current behaviour:** `detect()`, `rewrite()`, and `rewriteWithSemConv()` each
  construct a **fresh `SourceCode`**, so the WeakMap (keyed by object identity) never
  hits across them. Identical source is parsed 2× when a caller does
  `detect($code)` then `rewrite($code)`. The docblock promise of "parse exactly once
  across detect → rewrite → SemConv" is not delivered.
- **Fix options:** (A) key the cache by **content hash** — simplest, directly fixes the
  cross-call case; (B) thread one `SourceCode` instance through the pipeline — keeps
  WeakMap semantics. **Recommend (A).**

**Implementation steps**

1. In `ParsedSourceCache`, replace the `WeakMap` field with
   `private array $store = [];` (keyed by content hash).
2. In `get()/put()/has()`, compute `$key = $code->identity();` and use `$key` as the
   array index. Keep the method signatures (`SourceCodeInterface $code`) unchanged.
3. Delete `use WeakMap;` if now unused; rewrite the class docblock: *"Memoizes a parsed
   scan result keyed by the source's content hash (`SourceCode::identity()`); bounded
   by the number of distinct sources scanned during this Detector's lifetime."*
4. *(Optional, pairs with B)* Have `Detector::scanSource` accept a `SourceCodeInterface`
   that the caller reuses; keep the public `Disenchanter` methods creating one `SourceCode`
   but consider an overload that reuses an instance.
5. **Verify:** unit test — two *distinct* `SourceCode` instances with equal content hit
   the cache on the second `scanSource()` (spy the parser so `parse()` is invoked once).
   Integration test — `detect($code)` followed by `rewrite($code)` triggers exactly one
   `ParserFactory::build()` per `Detector` lifetime for that content.

---

### F2 — De-duplicate `nameFor()`  ·  ✅ Done  ·  REVIEW #2

- **Location:** `src/Disenchanter.php:72-79`, `src/SameClassConstantStrategy.php:56-65`,
  `src/PerClassConstantStrategy.php:56-65`.
- **Principle:** DRY, correctness risk.
- **Current behaviour:** the *"string → `generator`; number → `numericGenerator`"*
  branch is implemented **three times**. The `Candidate` value object already carries
  `constantName`, yet the placement strategies ignore it and recompute from
  `occurrences[0]`. A future naming-rule change must be made in three places or names
  silently diverge between detection and placement.
- **Fix:** one `NameResolver` port composing both generators; all three sites call it.

**Implementation steps**

1. Add `Contract\ConstantNameResolverInterface::resolve(LiteralInterface $lit): string`.
2. Implement `final readonly class ConstantNameResolver implements
   ConstantNameResolverInterface` injecting `ConstantNameGeneratorInterface` +
   `NumericNameGeneratorInterface`; its single method holds the branch
   (`LiteralKind::String → $generator->generate(...)` else
   `$numericGenerator->generate($lit, $lit->namingContext) ?? $lit->value`).
3. Inject `ConstantNameResolverInterface` into `Disenchanter`, `SameClassConstantStrategy`,
   `PerClassConstantStrategy` (wire via `DisenchanterFactory` / `DetectorFactory`).
4. Delete the three `nameFor()` methods; replace with `$this->resolver->resolve($lit)`.
5. **Verify:** unit test `ConstantNameResolver` (string + numeric + null-fallback).
   Mutation check — flip the branch in the one implementation and confirm all three
   call sites still produce the original names (snapshot/characterisation test on
   `examples/12_magic_numbers.php` output).

---

### F3 — `ConstantPlacement->type` string coupling  ·  ✅ Done  ·  REVIEW #3

- **Location:** `src/ValueObject/ConstantPlacement.php:17`,
  `src/ValueObject/ConstantPlacementInterface.php:19-24`,
  `src/SemConvCollector.php:87` (`LiteralKind::from($placement->type)`),
  `src/LineBasedRewriter.php:120-125` (emits `$placement->type` directly).
- **Principle:** type safety, DRY (single canonicalisation), correctness.
- **Current behaviour:** `type` is a `string` whose *value* coincidentally equals the
  PHP keyword (`int`/`float`/`string` = `LiteralKind` case values). If `LiteralKind`
  cases were ever renamed, both consumers break with no compile-time signal.

**Implementation steps**

1. Add two pure helpers on `LiteralKind` (or a `ConstantType` VO):
   `LiteralKind::fromPlacementType(string $t): self` and
   `LiteralKind::toPhpKeyword(): string` (`Int → 'int'`, `Float → 'float'`,
   `String → 'string'`).
2. `LineBasedRewriter`: emit
   `LiteralKind::from($placement->type)->toPhpKeyword()` instead of the raw string.
3. `SemConvCollector`: route its `LiteralKind::from($placement->type)` through the same
   helper so there is exactly one mapping site.
4. **Verify:** test the mapping for all three kinds; a rename of a `LiteralKind` case now
   fails a test rather than silently emitting a wrong keyword.

---

### F4 — `CandidateGrouper` inline `new`  ·  ✅ Done  ·  REVIEW #4

- **Location:** `src/CandidateGrouper.php:20-21` (default `new ByValueGroupingStrategy()`
  / `new ByValueAndContextGroupingStrategy()`).
- **Principle:** DIP, DI discipline (AGENTS.md: no inline `new` of collaborators).
- **Current behaviour:** the grouper constructs its collaborators with default `new`,
  breaking the project's own factory/DI rule.

**Implementation steps**

1. Change `CandidateGrouper` constructor to require
   `GroupingStrategyInterface $strings, GroupingStrategyInterface $numbers` (no defaults).
2. In `DetectorFactory::create()`, pass
   `new ByValueGroupingStrategy()` / `new ByValueAndContextGroupingStrategy()` from the
   composition root.
3. **Verify:** `CandidateGrouper` source contains no `new`; `DetectorFactory` wires both
   strategies; `composer architecture` still green.

---

### F5 — `readonly` consistency  ·  ✅ Done  ·  REVIEW #5

- **Location:** `src/LineBasedRewriter.php`, `src/RoleClassifier.php`,
  `src/SameClassConstantStrategy.php`, `src/PerClassConstantStrategy.php` are `final
  class` but **not** `final readonly class` (despite being stateless).
- **Principle:** immutability as a compiler-enforced guarantee; consistency.
- **Current behaviour:** `Detector` (lazy parser state) and `MagicLiteralVisitor`
  (accumulator) are correctly *not* readonly; the four above are stateless yet omit
  `readonly`.

**Implementation steps**

1. Mark `LineBasedRewriter`, `RoleClassifier`, `SameClassConstantStrategy`,
   `PerClassConstantStrategy` as `final readonly class`.
2. Confirm `Detector` and `MagicLiteralVisitor` stay non-readonly (intentional).
3. **Verify:** PHPStan + `composer architecture` green; no behavioural change (these hold
   only injected, never-reassigned deps).

---

### F6 — `detect()` return-key semantics  ·  ✅ Done  ·  REVIEW #6

- **Location:** `src/Disenchanter.php:50,62`, `src/Contract/DisenchanterInterface.php:23`.
- **Principle:** API honesty / DX.
- **Current behaviour:** docblock says "keyed by literal value", but the key is the
  **grouping key** (`i:5000|signature…` for numbers). For strings it equals the value;
  for numbers it does not. A consumer relying on the key as "the literal value" is
  surprised.

**Implementation steps**

1. Update both docblocks: the array is keyed by the **grouping key**; consumers should
   read `Candidate::value` / `Candidate::constantName`, not rely on the key.
2. *(Optional hardening)* return a small `GroupedCandidates` VO (`key`, `value`,
   `constantName`, `occurrences`) instead of a bare `array<string, CandidateInterface>`
   to make the key's meaning explicit.
3. **Verify:** docblock matches behaviour; add a test asserting a numeric group's key
   contains the naming-context signature.

---

### F7 — `scanSourceStreaming` is nominal  ·  ✅ Done  ·  REVIEW #7

- **Location:** `src/Detector.php:104-107` (`yield from $this->scanSource($code)` after a
  full in-memory parse).
- **Principle:** honesty of stated "streaming" capability; future large-file support.
- **Current behaviour:** the method materialises the entire AST, then yields the array.
  There is no incremental I/O or parsing, so for a very large single file the whole AST
  is still built.

**Implementation steps**

1. Keep the method but rewrite its docblock: *"convenience generator over the in-memory
   result; not incremental I/O."* Remove any "streaming performance" claim from the class
   docblock (`Detector.php:31-37`).
2. *(Future, only if a large-file CLI lands)* implement true streaming: a file-handle
   reader + node-visitor that yields per node, or chunked `token_get_all` + incremental
   parse. Defer until §5/§10/§17 work introduces multi-file scanning.
3. **Verify:** behaviour unchanged; docblock accurate.

---

### F8 — Bound input size (robustness / security)  ·  ✅ Done  ·  REVIEW #8

- **Location:** `src/Detector.php:81` (unbounded `parse()`), `AGENTS.md` security
  requirement for bounded payloads.
- **Principle:** security hardening, denial-of-service resistance.
- **Current behaviour:** any source — however large — is fully parsed and held as an AST
  + literal list. No max-size guard.

**Implementation steps**

1. Add `int $maxSourceBytes` (default e.g. `1_048_576`, or `0`/`null` = unbounded) to
   `Detector`'s constructor (injected via `DetectorFactory`).
2. At the top of `scanSource()`, `if ($this->maxSourceBytes > 0 && strlen($code->code) >
   $this->maxSourceBytes) throw new SourceTooLargeException(...)` (new domain exception,
   pairs with F13/§13).
3. **Verify:** unit test that a source exceeding the limit throws; a source under the
   limit parses normally; default (`0`) leaves behaviour unchanged.

---

### 2.1 Implementation log — REVIEW cluster (F1–F8)

Implemented in one batch; all gates green: PHPStan level 9 (0 errors),
PHPArkitect (0 violations), php-cs-fixer (0 files), 59 unit tests pass.

| Finding | Files touched | Notes |
|---|---|---|
| **F1** parse-once | `src/ParsedSourceCache.php`, `src/Detector.php` (docblock) | Cache now keys by `SourceCode::identity()` (content hash) instead of WeakMap object identity, so `detect`→`rewrite`→`rewriteWithSemConv` (each wraps code in a fresh `SourceCode`) parse exactly once. Regression: `DetectorCacheTest::testEqualSourceCodeParsedExactlyOnceAcrossDistinctObjects`. |
| **F2** `nameFor()` DRY | new `src/Contract/ConstantNameResolverInterface.php`, `src/ConstantNameResolver.php`; `src/Disenchanter.php`, `src/PerClassConstantStrategy.php`, `src/SameClassConstantStrategy.php`, `src/Factory/DisenchanterFactory.php`, `src/Factory/ConstantPlacementStrategyFactory.php`, `examples/bootstrap.php` | Single resolver composes `ConstantNameGenerator` + `NumericNameGenerator`; the three duplicated `nameFor()` methods removed and replaced by `resolver->resolve()`. |
| **F3** `type` coupling | `src/ValueObject/LiteralKind.php`, `src/ValueObject/ConstantPlacement.php`, `src/ValueObject/ConstantPlacementInterface.php`, `src/LineBasedRewriter.php`, `src/SemConvCollector.php` | Added `LiteralKind::toPhpKeyword()` + `LiteralKind::fromKind()`; `ConstantPlacement::typeKeyword()` is the single keyword mapping; `LineBasedRewriter` emits via it. Enum deliberately kept out of the port interface (PHPArkitect one-directional rule) — collector rehydrates via `fromKind()`. |
| **F4** grouper DI | `src/CandidateGrouper.php`, `src/Factory/DetectorFactory.php`, `tests/Unit/CandidateGrouperTest.php` | Both grouping strategies now injected from the composition root; `CandidateGrouper` no longer `new`s collaborators. |
| **F5** `readonly` | `src/RoleClassifier.php` | `RoleClassifier` is now `final readonly` (the other three were already); gives `CandidateFilter`/`ConstantNameGenerator`/`NumericNameGenerator` a consistent stateless guarantee. |
| **F6** `detect()` key | `src/Disenchanter.php` | Docblock corrected: return is keyed by the **grouping key** (value for strings, value+naming-context for numbers), not "literal value". |
| **F7** streaming doc | `src/Detector.php` | `scanSourceStreaming()` docblock now states honestly it is a generator wrapper over an up-front full parse, not incremental parsing. |
| **F8** input bound | new `src/Contract/DisenchantItExceptionInterface.php`, `src/Exception/SourceTooLargeException.php`, `src/Detector.php`, `tests/Unit/DetectorCacheTest.php` | `Detector` rejects sources > `MAX_SOURCE_BYTES` (1 MiB, overridable) before parsing; throws `SourceTooLargeException` (marker `DisenchantItExceptionInterface`). Regressions: `testSourceExceedingBoundThrows`, `testMaxSourceBytesConstantIsSane`. |

> Note: F8 pairs with §3.6 (domain exception hierarchy) — `SourceTooLargeException`
> landed there; `SemConvCollector`'s collision still throws a generic `RuntimeException`
> and is tracked under §3.6.

---

## 3. Remaining original-plan items (re-scoped to current gaps)

These carry forward from the original plan; status and remaining steps only.

### 3.1 Namespace split + layered enforcement  (old §1/§5/§6/§16, partial)

- **Goal:** introduce `src/Core/`, `src/Adapter/` so the dormant PHPArkitect layered
  rules activate; `Detector`/`LineBasedRewriter`/collectors/visitors move under
  `Adapter\`; `Disenchanter` under `Core\`; ports stay in `Contract\`.
- **Steps:**
  1. Create `src/Core/Disenchanter.php` (move existing class; keep `DisenchanterInterface`
     in `Contract\`).
  2. Create `src/Adapter/` and move `Detector`, `LineBasedRewriter`,
     `SemConvCollector`, `SemConvAliasRewriter`, `RoleClassifier`, `MagicLiteralVisitor`,
     `NamingContextExtractor`, `ConstantNameGenerator`, `NumericNameGenerator`,
     `CandidateFilter`, `CandidateGrouper`, the strategies, and the factories there
     (PSR-4 already covers sub-namespaces).
  3. Run `composer architecture`; the layered `where(...)->mayDependOnComponents(...)`
     rules now apply. Fix any violation (Adapter must not depend on `Core`; `Core` only
     on `Contract`/`ValueObject`).
  4. **Verify:** `composer architecture` green *with layered rules active*; PHPStan +
     autoload clean.

### 3.2 Property hooks on `SemConvConfig` + `LiteralRole` enum  (old §7, partial)

- **Steps:**
  1. `SemConvConfig`: make `outputPath` a **virtual property** via a `get` hook
     (`get => $this->outputPathOverride ?? $this->interfaceName . self::DOT .
     self::PHP_FILE_EXTENSION;`), storing only the optional override.
  2. Optionally expose `fullyQualifiedName` as a virtual `get` property.
  3. Introduce `LiteralRole` backed enum for the role magic-strings; replace the
     `string $role` field/comparisons in `StringLiteral`/`NumberLiteral`,
     `RoleClassifier`, `CandidateFilter` with the enum (removes typo-prone literals).
  4. **Verify:** PHPStan level 9; a test asserting role enum round-trips through visitor
     → filter → grouper.

### 3.3 Parser as Lazy Object  (old §8, partial → optional)

- Current code uses **first-use lazy init** (`Detector.php:73-75`), which is vendor-free
  and clear. Keep it. **Optional enhancement:** wrap the built `Parser` in
  `ReflectionClass::newLazyGhost()` so it initialises on first `parse()` (useful when a
  `Detector` is constructed but a run ends early). Low priority; manual init is
  acceptable given the `Lazy` API churn across PHP 8.4→8.5.

### 3.4 DRY — constant-declaration rendering  (old §11, not started)

- **Steps:**
  1. Add `ValueObject\ConstantDeclaration` (`name`, `value`, `visibility`) with
     `toCodeLine(int $indent = 4): string` centralising the `var_export` + indentation.
  2. `LineBasedRewriter` and `SemConvCollector` call it.
  3. **Verify:** both emit identical lines to today; one test per visibility.

### 3.5 DRY — `use`-statement insertion & file discovery  (old §10/§12, not started)

- **Steps:**
  1. Extract `NamespaceUseInserterTrait::insertUseStatement(string $code, string $fqcn):
     string` (regex anchors from `SemConvAliasRewriter`) for reuse by future generators.
  2. *(Only when a CLI/finder lands)* add `Contract\PhpFileFinderInterface` +
     `Adapter\RecursivePhpFileFinder`; optional `PhpFileScanningTrait`.
  3. **Verify:** `SemConvAliasRewriter` behaviour unchanged; finder returns the same
     file set as the old lab scripts.

### 3.6 Domain exception hierarchy  (old §13, not started) — pairs with F8

- **Steps:**
  1. Add `Contract\DisenchantItExceptionInterface extends Throwable` (marker) +
     `SemConvCollisionException`, `SourceTooLargeException` (F8), `ParseFailureException`,
     `DuplicateConstantException`.
  2. `SemConvCollector` throws `SemConvCollisionException`; `Detector` throws
     `SourceTooLargeException`.
  3. **Verify:** callers can catch `DisenchantItExceptionInterface`; no generic
     `RuntimeException` remains in the public path.

### 3.7 OCP — strategy registry  (old §14, not started)

- **Steps:**
  1. Add `ConstantPlacementStrategyInterface::supports(int $distinctClassCount): bool`.
  2. Rename `AutoConstantPlacementStrategy` → `CompositeConstantPlacementStrategy`, inject
     `iterable<ConstantPlacementStrategyInterface>`, pick the first `supports()`.
  3. **Verify:** adding a strategy = new adapter + registration, no edit to the composer.

### 3.8 Hygiene — first-class callables & native return types  (old §15, partial)

- **Steps:** replace closure callbacks with first-class callables
  (`StringLiteral::value(...)`); add native return types to all port methods. **Verify:**
  PHPStan level 9, php-cs-fixer clean.

### 3.9 ADR documentation  (old §16, partial)

- **Steps:** write `docs/ADR-0001`…`ADR-0005` (Ports & Adapters; Factories/DI; PHP 8.4
  features; PHPArkitect enforcement; Execution Model). The `phparkitect.php` rules
  already exist and pass; this only records the *decisions*.

### 3.10 Execution Model  (old §17, not started)

- **Steps:**
  1. Add `Contract\Execution\ExecutionRecorderInterface` (no-op `NullExecutionRecorder`
     default) so Core has no mandatory integration.
  2. Add immutable VOs `ValueObject\Execution\`:
     `DisenchantContext`, `DisenchantOccurrence` (lowercase, versioned names),
     `DisenchantObservation`, `DisenchantMeasurement`, `DisenchantSnapshot`.
  3. Structure `Core\Disenchanter` as explicit Activities (scan → decide → rewrite →
     collect-semconv → alias-semconv), each recorded via the port. Occurrence names
     lowercase + versioned (ADR-0011).
  4. Future RunIt = a separate bridge package; `Core` never imports `ItPack\RunIt\*`
     (enforced by PHPArkitect).
  5. **Verify:** `NullExecutionRecorder` makes runs identical with/without a recorder;
     artifacts are immutable + `Normalizable`; architecture gate enforces no `RunIt`
     import.

---

## 4. Consolidated step-by-step roadmap (ordered)

Each row is independently shippable and keeps the test suite green.

| # | Task | From | Severity | Effort | Verification |
|---|------|------|----------|--------|--------------|
| 1 | ✅ F1 cache by content hash | REVIEW #1 | 🔴 | S | parser invoked once per distinct source |
| 2 | ✅ F2 `ConstantNameResolver` | REVIEW #2 | 🔴 | M | one branch site; names unchanged |
| 3 | ✅ F3 `LiteralKind` canonicalisation | REVIEW #3 | 🟠 | S | mapping test for all kinds |
| 4 | ✅ F4 inject grouper strategies | REVIEW #4 | 🟠 | S | no `new` in `CandidateGrouper` |
| 5 | ✅ F5 `readonly` on stateless services | REVIEW #5 | 🟡 | S | PHPStan + architecture green |
| 6 | ✅ F6 fix `detect()` key doc | REVIEW #6 | 🟡 | S | doc matches behaviour |
| 7 | ✅ F7 honest streaming docblock | REVIEW #7 | 🟡 | S | doc accurate |
| 8 | ✅ F8 `SourceTooLargeException` + size guard | REVIEW #8 | 🟠 | S | throws over limit; pairs w/ §3.6 |
| 9 | ✅ §3.6 domain exceptions | old §13 | 🟠 | S | catch by marker interface |
| 10 | ✅ §3.4 DRY const rendering VO | old §11 | 🟠 | S | identical output |
| 11 | ✅ §3.1 namespace split + layered rules | old §1/§5/§6/§16 | 🔴 | M | layered architecture green |
| 12 | ✅ §3.2 `SemConvConfig` hooks + `LiteralRole` enum | old §7 | 🟠 | M | enum round-trips |
| 13 | ✅ §3.7 OCP strategy registry | old §14 | 🟡 | S | new strategy w/o edit |
| 14 | ✅ §3.5 `use`-insertion trait + finder | old §10/§12 | 🟡 | M | behaviour unchanged |
| 15 | §3.3 optional Lazy GhostObject parser | old §8 | 🟡 | S | lazy init preserved (optional) |
| 16 | ✅ §3.8 hygiene (callables / return types) | old §15 | 🟡 | S | php-cs-fixer + PHPStan |
| 17 | ✅ §3.9 write ADRs 0001–0005 (+0011) | old §16 | 🟠 | S | docs present |
| 18 | ✅ §3.10 Execution Model + recorder port | old §17 | 🟠 | M | RunIt-free, no-op recorder |

**Recommended first PRs:** #1 → #2 → #3 → #4 → #5 → #8/#9 (the REVIEW-driven correctness,
consistency, and robustness cluster) — **F1–F8 implemented**; then #11 (the structural
split that activates the layered gate), then the remaining original items.

---

## 5. Verification gate (per refactor)

- **PSR-4 / autoload:** `composer dump-autoload` clean; one class per file, filename ==
  class name (holds after the §3.1 namespace move).
- **PHPStan:** level 9, 0 errors (`composer analyze`).
- **php-cs-fixer:** `composer format:check` → 0 files needing fixing.
- **PHPArkitect:** `composer architecture` → 0 violations. After §3.1 this must include
  the **active** layered rules (`Contract`/`Core`/`Adapter`/`ValueObject` dependency
  allowances) plus the three custom `Expression` rules (interfaces depend only on
  interfaces; classes are `final`; final classes are backed by interfaces) and the §0
  factory `build()` / no-static-product guard.
- **Unit tests (mock-based per port; fixture-based per adapter):** specifically cover
  the REVIEW fixes — F1 (WeakMap/content cache fires across `detect`→`rewrite`), F2
  (`ConstantNameResolver` is the single naming site), F3 (`LiteralKind` mapping), F8
  (size guard throws). The suite stays green across every step; each step is
  independently shippable.
- **No adapter imports a Core class; no Core class imports a vendor type or
  `ItPack\RunIt\*`** (ADR-0001 / ADR-0002 / ADR-0005 preserved). The `RunIt` exclusion is
  the execution-model guardrail — RunIt stays an optional future adapter, never a Core
  dependency.
- **Execution-model seam (after §3.10):** Core records exclusively through
  `ExecutionRecorderInterface` (default `NullExecutionRecorder` = no-op), so the library
  runs identically with or without a recorder. Domain artifacts are immutable and
  `Normalizable`, and occurrence names are lowercase + versioned, so a future
  `it-pack/run-it-disenchant-it` bridge maps them onto RunIt with no Core change.
