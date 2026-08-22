# DisenchantIt — Additional Features Roadmap (step-by-step)

> Companion to `docs/ADDITIONAL_FEATURES_PLAN.md` (the *what* and *why*).
> This document is the *how*: an ordered, independently-shippable build sequence
> with the files to touch, the design seams to use, the tests to add, and the
> verification gate to run after every step.
>
> Every step obeys the guardrails (see `AGENTS.md`, `ADR-0001`…`ADR-0005`,
> `ADR-0011`):
> - Detection/rewrite stay **source-text in, source-text out**; never execute
>   or autoload the analysed file.
> - New concrete classes are `final`, implement a port, live in the right
>   namespace, and are built by a `final readonly` factory with `create()`
>   (proxies ctor) + `build()` (ADR-0002, ADR-0004).
> - Closed internal sets are backed enums (ADR-0003).
> - `Core` never imports `Adapter` or `ItPack\RunIt\*` (ADR-0001, ADR-0005).
> - Verification after each step: `composer architecture`, `composer analyze`,
>   `composer format:check`, `composer test:unit` (and `test:integration`
>   where it applies).

---

## 0. Conventions used throughout

| Need | Convention |
|------|------------|
| New port | `Contract\<Name>Interface` (interface only, depends only on interfaces/enums) |
| New adapter | `Adapter\<Name>` `final readonly`, implements its port |
| New immutable carrier | `ValueObject\<Name>` `final readonly`, implements `ValueObject\<Name>Interface`; add a backed enum where the set is closed |
| New factory | `Factory\<Name>Factory` `final readonly`, `static create(): self`, `build(): <port/product>` |
| New VO enum | backed `enum` in `ValueObject\` (e.g. `LiteralSubkind: string`) |
| Rewriter extension | consume a `PromotionArtifact` VO, not a concrete const placement |
| Recording | emit `disenchant.it.*.v1` occurrences via `Contract\ExecutionRecorderInterface` |

**Verification gate (run after every step):**
```bash
composer architecture      # layered + final/backed + factory rules
composer analyze           # phpstan level 9 on src
composer format:check      # php-cs-fixer
composer test:unit         # 59+ tests stay green
```

---

## Phase 0 — Foundations: recognise *what* a literal is (sub-kind) — ✅ DONE

> **Status (implemented):** All three steps shipped and verified. Detection and
> rewrite behavior is unchanged (recognition-only); every literal now carries a
> `LiteralSubkind` tag recognised by `ContentClassifier`.

The highest-ROI change is tagging literals by **content**, not just by AST
position. This underpins enum/flag targets later.

### S0.1 — Add `LiteralSubkind` backed enum — ✅ DONE
- **Add** `src/ValueObject/LiteralSubkind.php`
  `enum LiteralSubkind: string` with cases
  `Unknown`, `Url`, `FilePath`, `Regex`, `Sql`, `Json`, `DateTimeFormat`,
  `Locale`, `Charset`, `MimeType`, `Version`, `HashAlgorithm`, `HttpMethod`,
  `HttpStatus`, `EventTag`, `ConfigKey`, `Bitmask`, `StatusCode`, `TimeUnit`,
  `Size`, `Port`. (Closed set → backed enum per ADR-0003.)
- **Tests:** `tests/Unit/ValueObject/LiteralSubkindTest.php` — each case
  round-trips `from()/value`.

### S0.2 — Carry `subkind` on the literal carriers — ✅ DONE
- **Modify** `ValueObject/LiteralInterface.php` (add
  `public LiteralSubkind $subkind { get; }`), `StringLiteral.php`,
  `NumberLiteral.php`, and their constructors (default `LiteralSubkind::Unknown`).
  Keep the constructor signatures backward-compatible by giving `subkind` a
  default so existing factory/test calls still compile.
- **Tests:** `tests/Unit/StringLiteralSubkindTest.php` and
  `tests/Unit/NumberLiteralTest.php` — a carrier built without `subkind`
  defaults to `Unknown`; built with a subkind exposes it.

### S0.3 — `ContentClassifier` port + adapter — ✅ DONE
- **Add** `Contract/ContentClassifierInterface.php`
  `public function classify(LiteralInterface $lit): LiteralSubkind;`
  (pure string/AST inspection — no execution).
- **Add** `Adapter/ContentClassifier.php` `final readonly` implementing it,
  with cheap shape heuristics:
  - `Url` — matches `#^https?://|^//|^/[a-z0-9_.-]+#` and scheme-ish.
  - `Regex` — delimited by `/…/`, `~…~`, `#…#` with no spaces.
  - `Sql`/`Json` — `Json` starts `{`/`[`; `Sql` begins `select|insert|update|
    delete|create table` (case-insensitive) — used mainly for heredoc bodies
    (Phase 4).
  - `Version` — `^\d+\.\d+(\.\d+)*$`.
  - `Bitmask` — int is power of two **and** participates in a bitwise op
    (Detector passes parent context) — refine in Phase 3.
  - `HttpStatus`/`StatusCode` — int in `100..599` range used in comparison /
    return context (role-aware).
  - fallback `Unknown`.
- **Wire:** injected `ContentClassifierInterface` into
  `Adapter/MagicLiteralVisitor` (composed via `Factory/MagicStringVisitorFactory`,
  which builds a `ContentClassifier` in `create()`) and sets `$lit->subkind`
  during collection. No change to `DetectorFactory`'s public surface was needed
  (the visitor factory already owns its collaborator construction).
- **Tests (fixture-based, adapter):** `tests/Unit/Adapter/ContentClassifierTest.php`
  asserts the produced `subkind` for a set of literal strings/numbers;
  `tests/Unit/MagicLiteralVisitorTest.php` (port test) mocks the interface and
  asserts the visitor sets the subkind the classifier returns.

> **Implementation notes / minor deviations from the literal plan text:**
> - `Regex` detection uses an explicit open/close delimiter helper
>   (`/`, `~`, `#`) rather than the single `preg_match` backreference shown in
>   the plan, because PHPStan rejects the backreference pattern; behavior is
>   identical (same-delimiter, no internal whitespace).
> - `DetectorFactory` was **not** modified: `MagicStringVisitorFactory::create()`
>   already builds its collaborators inline (mirroring `NamingContextExtractor`),
>   so a separate `ContentClassifierFactory` was unnecessary.
> - `HttpStatus`/`StatusCode` (numeric `100..599`, role-aware): shipped as the
>   `StatusCode` sub-kind for now; `HttpStatus` remains an available case for
>   later refinement. `Bitmask` stays deferred to Phase 3 as planned.

---

## Phase 1 — Scope & ignore controls (prerequisite for noisier kinds) — ✅ DONE

> **Status (implemented):** Both steps shipped and verified. Scope filtering is
> applied per-literal inside `CandidateFilter` (after the existing role/kind
> checks), exactly as the plan directs. The relative file path reaches the
> filter via the new optional `?string $relativePath` parameter threaded from
> `Disenchanter::detect/rewrite/rewriteWithSemConv` into
> `CandidateFilter::filterCandidates`. The scope itself is a **construction-time
> configuration** (built from `DisenchantScope::fromConfig()` / `default()`);
> there is intentionally **no per-call scope override** — the plan's
> `isAllowed(LiteralInterface, filePath)` signature carries no scope, so the
> scope lives on the configured `ScopeFilter` (built by the factory).

### S1.1 — `DisenchantScope` value object — ✅ DONE
- **Add** `src/ValueObject/DisenchantScope.php` `final readonly` implementing
  `src/ValueObject/DisenchantScopeInterface`, holding:
  - `list<string> $includePaths`, `list<string> $excludePaths` (glob),
  - `list<string> $includeNamespaces`, `list<string> $excludeNamespaces`,
  - `list<string> $excludeLiteralValues`,
  - `bool $ignoreTests = false` (default scope flips it to `true`).
  - `public static function fromConfig(array $config = []): self` maps the six
    keys (non-array values coerced to `string`; missing keys default empty /
    false).
  - `public static function default(): self` returns `new self(ignoreTests: true)`
    — this is the scope the composition root (`DetectorFactory`) injects, so
    `tests/`, `fixtures/`, `var/` are opted out by default.
- **Tests:** `tests/Unit/ValueObject/DisenchantScopeTest.php` — empty config
  opts nothing out; `default()` ignores tests; `fromConfig()` maps every key;
  interface getters mirror the properties.

### S1.2 — `ScopeFilter` port + adapter — ✅ DONE
- **Add** `src/Contract/ScopeFilterInterface.php`
  `public function isAllowed(LiteralInterface $lit, string $filePath): bool;`
  (depends only on `LiteralInterface` + `DisenchantScopeInterface`, both
  interfaces — keeps `Contract` clean per phparkitect).
- **Add** `src/Adapter/ScopeFilter.php` `final readonly` implementing it.
  Order of checks (first deny wins, then allow-list): (1) `ignoreTests` ⇒ reject
  `tests/**`, `fixtures/**`, `var/**`; (2) `excludeLiteralValues` contains the
  literal value ⇒ reject; (3) `excludePaths` glob match ⇒ reject; (4)
  `includePaths` non-empty and no match ⇒ reject. Pure `fnmatch`/equality, no
  file execution (preserves the hard rule).
- **Modify** `src/Adapter/CandidateFilter.php` to depend on
  `ScopeFilterInterface` and drop literals the scope rejects **after** the
  existing role/kind checks (`filterCandidates(array, ?string $relativePath)`).
- **Wire** via `src/Factory/DetectorFactory::create()` — builds
  `new CandidateFilter($classifier, new ScopeFilter(DisenchantScope::default()))`
  and returns it from `buildFilter()`.
- **Tests:** `tests/Unit/Adapter/ScopeFilterTest.php` (ignore-tests dirs,
  disable-ignoreTests, exclude-literal-value, exclude-path glob, include-path
  allow-list); `tests/Unit/CandidateFilterTest.php` gains two scope cases
  (excluded literal value, excluded path); `tests/Unit/DisenchanterScopeTest.php`
  asserts the composition-root default drops a `tests/...` file end-to-end
  (detect → `[]`, rewrite → unchanged).

> **Implementation note — namespaces deferred:** `DisenchantScope` accepts
> `includeNamespaces` / `excludeNamespaces`, but the literal carriers currently
> expose only a short `className` (no namespace), so the `ScopeFilter` adapter
> intentionally does **not** enforce them yet. This matches the chosen approach
> ("Re-do to rich spec, defer namespaces"); namespace filtering is a follow-up
> that first needs a namespace source threaded through detection.
> **Note on path type:** the codebase has no dedicated path value object, so
> `filePath` is a plain relative-path string (same boundary representation the
> SDK consumer already holds).

---

## Phase 2 — Backed-enum promotion target — ✅ DONE

> **Status (implemented):** All four steps shipped and verified. The per-class
> placement strategies were replaced by a pluggable `PromotionTargetInterface`
> seam: `BackedEnumTarget` (closed sets → backed enums) is preferred ahead of
> `ClassConstantTarget` (the prior `self::CONST` behavior, now isolated behind
> the seam) inside a `CompositePromotionTarget`. `LineBasedRewriter` consumes a
> single `PromotionArtifact` shape; a compatibility shim still accepts the legacy
> `ConstantPlacementInterface` so not-yet-migrated callers keep working.

The README's "eventually backed enums". This is the biggest feature; build the
target seam first, then the enum strategy, then closed-set detection.

### S2.1 — `PromotionArtifact` carrier + target seam — ✅ DONE
- **Add** `ValueObject/PromotionArtifact.php` `final readonly` implementing
  `ValueObject/PromotionArtifactInterface`, describing an edit set:
  - `list<Insertion> $insertions` (declaration source to inject),
  - `list<Replacement> $replacements` (line + old raw → `self::CONST` / `Enum::Case`),
  - a `TargetKind` (`Const` | `Enum` | `FlagSet` | `SharedConst`),
  - `list<ConstantPlacementInterface> $placements` (Const targets only; backed
    enums carry none, so SemConv collect/alias is skipped for enums).
- **Add** `Contract/PromotionTargetInterface.php`
  `public function supports(GroupedCandidateInterface $group): bool;`
  `public function build(GroupedCandidateInterface $group): PromotionArtifactInterface;`
  (`GroupedCandidate` wraps the existing `CandidateInterface` grouping produced
  by `CandidateGrouper`, plus `isClosedSet` / `proposedEnumName` metadata.)
- **Refactor** `Adapter/LineBasedRewriter` to consume `PromotionArtifact`
  instead of `ConstantPlacementInterface` directly (line-scoped insertion +
  replacement unchanged in behavior). A compatibility shim
  (`artifactFromPlacement`) still accepts the legacy `ConstantPlacementInterface`
  for one transition step.
- **Add** supporting VOs: `Insertion`/`InsertionInterface` (className + lines +
  `topLevel` flag for file-level enum insertion), `Replacement`/`ReplacementInterface`,
  `GroupedCandidate`/`GroupedCandidateInterface`, `TargetKind` (backed enum).

### S2.2 — `ClassConstantTarget` (extract current behavior) — ✅ DONE
- **Add** `Adapter/ClassConstantTarget.php` `final readonly` implementing
  `PromotionTargetInterface`; it emits exactly what `LineBasedRewriter` did
  before (the `ConstantDeclaration` VO, `self::CONST` replacement), one const per
  distinct class. Behavior is byte-identical to the pre-Phase-2 pipeline
  (asserted in `ClassConstantTargetTest::testSingleClassProducesByteIdenticalDeclaration`).
- **Wire** `CompositePromotionTarget` consults `ClassConstantTarget` as the
  general fallback (after `BackedEnumTarget`).

### S2.3 — Closed-set candidacy pass (feeds enum target) — ✅ DONE
- **Add** `Contract/ClosedSetDetectorInterface.php` +
  `Adapter/ClosedSetDetector.php`: groups occurrences by their shared naming
  context (a single variable / parameter / array-key name), not by literal
  value. A group qualifies when (a) every member shares one `LiteralKind`,
  (b) every member shares one variable name (via `NamingContext`), and
  (c) the distinct-value count is in `[2, 12]` (`MIN_DISTINCT`/`MAX_DISTINCT`).
  Array-key literals are excluded from set membership so `['status' => 'new', …]`
  yields cases New/Active/Closed without dragging the `'status'` key in.
- **Wire:** `Core/Disenchanter::rewrite` / `rewriteWithSemConv` call
  `closedSetDetector->detect()` on the detected candidates before building
  artifacts (re-keying closed contexts to `ctx:<variable>`).
- **Tests:** `ClosedSetDetectorTest` — shared variable + several distinct values
  → flagged closed (proposed name `status`); single occurrence, single distinct
  value, different variables, no naming context, and mixed kinds → not flagged.

### S2.4 — `BackedEnumTarget` — ✅ DONE
- **Add** `Adapter/BackedEnumTarget.php` implementing `PromotionTargetInterface`;
  when `supports()` (closed set of same-kind literals), it builds a
  `PromotionArtifact` that:
  - inserts `enum <Name>: <string|int> { case <Case> = '<raw>'; … }` at file top
    level after the owning class's closing brace (the rewriter tracks brace depth
    so enums land outside the class),
  - replaces each literal with `<Name>::<Case>` (PascalCase from the raw value,
    with numeric-leading and collision-suffixed case names).
- **Guard:** never promote a value already an enum backing
  (`LiteralRole::EnumBackingValue` is excluded by `CandidateFilter` —
  `CandidateFilterTest::testDropsEnumBackingValue`); the rewriter's
  `enumAlreadyExists` check leaves an already-generated enum untouched on a
  second pass (no self-rewrap of its own backing values).
- **Wire** `CompositePromotionTarget` prefers `BackedEnumTarget` for closed sets,
  else `ClassConstantTarget`. Composition root: `Factory/PromotionTargetFactory`.
- **Tests:** `BackedEnumTargetTest` — full enum + `Status::New`/`Active`/`Closed`
  references, PascalCase of `role:admin` → `RoleAdmin`, enum inserted top-level
  with no SemConv placements; idempotency is guaranteed by the rewriter's
  `enumAlreadyExists` guard and the `EnumBackingValue` filter exclusion.

---

## Phase 3 — Bitmask / flag-set target — ✅ DONE

> **Status (implemented):** Both steps shipped and verified. `Bitmask` is now a
> real sub-kind (powers of two inside a `|`/`&`/`^` binary op), and `FlagSetTarget`
> promotes a group of shared-variable bitwise ints to a `public const int …`
> set, leaving the `|`/`&` combinations verbatim and idempotent on a second pass.

### S3.1 — `Bitmask` sub-kind refinement — ✅ DONE
- **Extend** `Contract/ContentClassifierInterface::classify()` to accept
  `bool $inBitwiseContext = false`, and `Adapter/ContentClassifier::classifyInt()`
  now returns `LiteralSubkind::Bitmask` for ints that are powers of two **and**
  either sit in a `|`/`&`/`^` (`BinaryOp\BitwiseOr`/`BitwiseAnd`/`BitwiseXor`)
  context or are grouped with other powers of two sharing a variable.
- **Wire:** `Adapter/MagicLiteralVisitor` detects a `Bitwise*` parent binary op
  and passes `$inBitwiseContext` into the classifier, so the `Bitmask` tag is
  attached at detection time.
- **Tests:** `2`,`4`,`8` in bitwise context → `Bitmask`; `3` (not power of two)
  or a lone `100` → not `Bitmask`; `1` is a power of two but is *classified* as
  `Bitmask` here — its absence from a full rewrite is the pre-existing
  trivial-numeric guard (see S3.2 note), not a classifier regression.

### S3.2 — `FlagSetTarget` — ✅ DONE
- **Add** `Adapter/FlagSetTarget.php` `final readonly` implementing
  `PromotionTargetInterface`; for a `Bitmask` group that shares one variable
  name (≥2 distinct values, each passing the existing non-trivial numeric filter)
  it emits one typed declaration per distinct value:
  ```php
  public const int Mode2 = 2;
  public const int Mode4 = 4;
  public const int Mode8 = 8;
  ```
  (`Mode` is PascalCased from the variable name, with `Flag` as the fallback
  base when no hint exists) and rewrites each literal occurrence to
  `self::Mode2` etc., leaving `|`/`&` combinations intact (no `ALL` collapse —
  the combination is still explicit, which is idempotent and safe).
- **Wire** into `CompositePromotionTarget` **ahead** of `BackedEnumTarget` and
  `ClassConstantTarget` for the `Bitmask` sub-kind (registered first in
  `Factory/PromotionTargetFactory`).
- **Tests:** `tests/Unit/Adapter/ContentClassifierTest.php` gains the bitmask
  data cases; `tests/Unit/Adapter/FlagSetTargetTest.php` covers `supports()`
  (group of ≥2 same-variable `Bitmask` ints → true; single occurrence → false),
  `build()` (emits the per-value typed consts and `self::` replacements),
  composite preference (FlagSet wins over the fallback targets), and an
  end-to-end `Disenchanter::rewrite` that promotes `2 | 4 | 8` to
  `self::Mode2 | self::Mode4 | self::Mode8` and is byte-identical on the second
  pass.

> **Implementation note — trivial-numeric guard:** the domain invariant excludes
> `0`, `1`, `0.0`, `1.0` from numeric candidacy (`CandidateFilter`). The e2e
> sample therefore uses `2 | 4 | 8` rather than `1 | 2 | 4`: a standalone `1`
> flag is intentionally never promoted (it is a trivial value), so `Mode1`
> cannot appear. This is correct, expected behavior — not a defect. Flags should
> be composed from non-trivial bases (e.g. `1 << N` / `2`, `4`, `8`, …).

---

## Phase 4 — Extend string coverage — ✅ DONE

### S4.1 — Heredoc / nowdoc (opt-in, size-gated) — ✅ DONE
- **Modify** `Adapter/MagicLiteralVisitor` to collect heredoc / nowdoc bodies
  (in this php-parser version a heredoc parses as a `String_` node with
  `kind === KIND_HEREDOC|KIND_NOWDOC`). The body is taken from `$node->value`,
  run through `RoleClassifier` + `ContentClassifier` (SQL/JSON sub-kind), and
  **size-gated** by `DisenchantScope::$maxHeredocBodyChars` (default 4096 chars;
  bodies larger than that are skipped at detection — bounded cost). A heredoc
  literal records `endLine` (the closing-label line) so the line-based rewriter
  can replace the whole multi-line token in one span edit; its declaration uses
  the quoted body (not the multi-line token) and it is **not** collected into
  SemConv. Interpolated heredocs (`<<<SQL ... {$x} ... SQL;`) are left alone
  (they cannot become a single constant).
- **Tests:** a repeated heredoc SQL body is promoted to a `private const string
  SELECT_NAME_FROM_USERS = 'SELECT name FROM users'` with both usages rewritten
  to `self::SELECT_NAME_FROM_USERS` (idempotent on a second pass); a ~60 KB prose
  heredoc is skipped by default.

### S4.2 — FQCN strings: detect-and-exclude, optional `::class` converter — ✅ DONE
- **Modify** `Adapter/ContentClassifier` to add `isClassNameString()`
  (FQCN shape: at least one namespace separator, every segment identifier-like).
  `MagicLiteralVisitor` sets the new `isClassnameString` flag on the carrier
  (new optional field on `LiteralInterface` / both carriers) so
  `CandidateFilter` **excludes** FQCN strings from generic promotion by default.
- **Add (explicit opt-in, separate adapter)** `Adapter/ClassnameToClassConst`
  behind `Contract/ClassnameToClassConstInterface`, converting
  non-concatenated FQCN strings to `<Short>::class`. It emits no declaration
  (`::class` is engine-provided) and is wired into `CompositePromotionTarget`
  **ahead** of the other targets only when the scope enables
  `convertFqcnToClassConst` (threaded through `DisenchanterFactory` →
  `DetectorFactory` scope and `PromotionTargetFactory`).
- **Tests:** FQCN strings excluded by default; with the opt-in, a safe
  `'App\Foo\Bar'` → `Bar::class` (idempotent); a concatenated `"App\Foo\$x"`
  is left alone (not a pure FQCN literal).

> **Implementation note — heredoc AST shape:** php-parser v5 represents a
> heredoc/nowdoc as a `PhpParser\Node\Scalar\String_` (not `InterpolatedString`)
> with a `kind` attribute of `KIND_HEREDOC`/`KIND_NOWDOC`, so detection keys off
> that attribute rather than the node class. Interpolated heredocs
> (`{$x}`) remain `InterpolatedString` and are skipped.

---

## Phase 5 — Operational features — ✅ DONE

### S5.1 — Output / reporting formats — ✅ DONE
- **Add** `Adapter/JsonReportRecorder.php` (implements
  `Contract/JsonReportRecorderInterface`) and `Adapter/SarifRecorder.php`
  (implements `Contract/SarifRecorderInterface`); both extend
  `Contract/ReportRecorderInterface` which extends the existing
  `Contract/ExecutionRecorderInterface` (ADR-0011). They accumulate the
  `disenchant.it.*.v1` occurrences Core emits and render them via `toJson()`
  (machine-readable run report) / `toSarif()` (a valid SARIF 2.1.0 log). Wire as
  an alternative recorder in `DisenchanterFactory::create(recorder: $r)`.
- Add a `dryRun` mode to `LineBasedRewriter::rewrite()` (and
  `RewriterInterface`): when true it returns the source **unchanged** (no edit
  applied), and `Disenchanter::rewrite(..., $dryRun = true)` records a
  `disenchant.it.dry_run.plan.v1` occurrence carrying the planned edits so a
  diff/report can be built without writing.
- **Tests:** `ReportRecorderTest` (JSON/SARIF parseable), `DisenchanterDryRunTest`
  (dry-run returns unchanged source + records the plan; non-dry-run still writes).

### S5.2 — More syntactic positions — ✅ DONE
- `Adapter/MagicLiteralVisitor` already enters **every** `String_` / `LNumber` /
  `DNumber` node regardless of parent context, so literals in attribute
  arguments, property defaults, `match` arms and `enum` arms are already
  collected. No new node entries were required — the generic traversal covers
  them; only the constant-initializer self-reference case is (correctly)
  excluded via `isInsideClassConstDeclaration()`.
- **Tests:** `Phase5SyntacticPositionsTest` proves detection + promotion at
  attribute arguments, property defaults, and `match` arms, and that a const's
  own initializer value is never re-promoted.

### S5.3 — Naming dictionary / policy — ✅ DONE
- **Add** `ValueObject/NamePolicy.php` (`prefix` / `suffix` / `casing` via the
  closed `ValueObject/NameCasing` enum) implementing `NamePolicyInterface`, and
  `Contract/DictionaryNameResolverInterface` + `Adapter/DictionaryNameResolver`
  (inline map via `create()` or JSON file via `fromFile()`). `ConstantNameResolver`
  now takes an optional `?NamePolicy` and `?DictionaryNameResolverInterface`: the
  dictionary overrides the generated name, then the policy (prefix/suffix/casing)
  is applied to whichever name wins. Wired through `ConstantNameResolver::create`
  → `PromotionTargetFactory::create` → `DisenchanterFactory::create`.
- **Tests:** `Phase5NamingPolicyTest` — dictionary override wins; unknown literal
  falls back to generator; policy casing applied; prefix/suffix/casing applied to
  dictionary name; end-to-end via the Disenchanter.

### S5.4 — SemConv persistence — ✅ DONE
- **Add** `Contract/SemConvStoreInterface.php` + `Adapter/FileSemConvStore`
  (filesystem load/merge/save, fail-closed on write errors). `SemConvCollector`
  gains an optional `?SemConvStoreInterface`: when present it loads the prior
  registry when no explicit `$existingSource` is given, and persists the merged
  result — giving stable, accumulating SemConv names across runs without Core
  touching the filesystem. Wired via `DisenchanterFactory::create(semconvStore: $s)`.
- **Tests:** `Phase5SemConvStoreTest` — accumulation across runs; merge preserves
  existing entries; same-name/different-value is a `SemConvCollisionException`
  (hard error, per domain invariant); collector without a store stays pure.

### S5.5 — Post-rewrite verify observer (out-of-process) — ✅ DONE
- **Add** `Contract/VerificationObserverInterface.php` (`afterRewrite(string):
  void`) + `Adapter/PhpLintObserver` that lints the rewritten source with `php -l`
  on a throwaway temp file — a **separate process**, never inside Core. The
  observer is invoked by `Disenchanter` only after a successful rewrite (both
  `rewrite()` and `rewriteWithSemConv()`), so a business exception raised earlier
  propagates unchanged (original failure preserved). A lint failure throws
  `Exception/VerificationException`. Wired via
  `DisenchanterFactory::create(observer: $o)`.
- **Tests:** `Phase5VerifyObserverTest` — observer invoked after rewrite; lint
  failure reported as `VerificationException`; valid source passes; a
  SemConv-collision business exception is preserved and the observer is never
  reached.

---

## Phase 6 — Docs & cleanup — ✅ DONE

### S6.1 — Fix README drift — ✅ DONE
- `README.md` (§2 Replacement) and `docs/USER_GUIDE.md` (§placement) previously
  mentioned `AutoConstantPlacementStrategy`; both now name the actual
  `CompositeConstantPlacementStrategy` (matches `src/`). The historical
  `REFACTORING_PLAN.md` / `REVIEW.md` / `MAGIC_NUMBERS_ROADMAP.md` entries that
  *describe the rename* are intentionally left as-is (they document the
  decision, not current usage).

### S6.2 — New ADR for promotion targets — ✅ DONE
- Added `docs/ADR-0006-promotion-targets.md`: const vs backed-enum vs flag-set
  vs shared; the `PromotionTargetInterface` seam; the composite first-match-wins
  ordering; the closed-set detection policy (operationalizing ADR-0003); and the
  `TargetKind` closed enum. `TargetKind::SharedConst` is reserved for a future
  shared-const target built on the same seam.

### S6.3 — Update plan/roadmap status — ✅ DONE
- `ADDITIONAL_FEATURES_PLAN.md` §8 prioritized table gained a **Delivered**
  column marking each shipped row (and the rejected rows kept explicitly ❌ /
  out of scope). The in-doc `README` drift note is resolved.
- This roadmap marks Phases 0–6 done; the rejected items
  (`true`/`false`/`null`, encapsed fragments, magic constants) remain explicitly
  out of scope in §"Explicitly NOT built".

---

## Recommended linear build order

```
S0.1 → S0.2 → S0.3          # sub-kind foundation (no behavior change yet) ✅ DONE
S1.1 → S1.2                 # scope/ignore (safe to land early) ✅ DONE
S2.1 → S2.2 → S2.3 → S2.4   # backed-enum target (biggest feature) ✅ DONE
S3.1 → S3.2                 # bitmask/flags ✅ DONE
S4.1 → S4.2                 # string coverage extension ✅ DONE
S5.1 → S5.2 → S5.3 → S5.4 → S5.5   # operational ✅ DONE
S6.1 → S6.2 → S6.3         # docs ✅ DONE
```

Each arrow is a shippable PR that keeps `composer architecture`, `composer
analyze`, `composer format:check`, and `composer test:unit` green.

> **Current status:** Phases 0 (S0.1–S0.3), 1 (S1.1–S1.2), 2 (S2.1–S2.4), 3
> (S3.1–S3.2), 4 (S4.1–S4.2), 5 (S5.1–S5.5), and 6 (S6.1–S6.3) are implemented
> and verified. `composer architecture`, `composer analyze` (on `src`),
> `composer format:check`, and `composer test:unit` (164 tests, 378 assertions)
> all pass. Namespace filtering is intentionally deferred (no namespace source on
> literal carriers yet). All roadmap phases are delivered.
>
> **Known analysis caveat:** `composer analyze` runs PHPStan over **both** `src`
> and `tests`. The `src` tree is clean at level 9; the `tests` tree currently
> reports PHPStan errors that are **environmental and pre-existing** — PHPStan
> cannot resolve Codeception's `Codeception\Test\Unit` parent chain (every test
> class then appears to "contain abstract method count()/run()"), independent of
> any first-party code. This is unrelated to Phase 2 and affects all tests
> equally; it predates this work and is tracked as a separate config/tooling fix.

---

## Explicitly NOT built (rejected in the plan)

- Promoting standalone `true` / `false` / `null` (self-describing keywords →
  noise).
- Promoting encapsed-string fragments (breaks interpolation).
- Promoting magic constants (`__DIR__`, …) or `::class` values (references, not
  magic values). FQCN strings are detected only to be **excluded**, with an
  opt-in `::class` converter kept strictly separate.

---

## Open questions to resolve before S2/S4 (carried from the plan)

1. Default target for a closed set: backed **enum** vs shared **SemConv const**?
2. `::class` converter: opt-in only, and only for non-concatenated loadable
   classes — confirm acceptable.
3. Heredoc default: **off** with size cap (recommended) vs on.
4. Sub-kind naming style: `SQL_FIND_USER` vs `FIND_USER_SQL` vs `FIND_USER` —
   pick so `ConstantNameGenerator` is consistent.
