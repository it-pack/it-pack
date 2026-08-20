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

## Phase 0 — Foundations: recognise *what* a literal is (sub-kind)

The highest-ROI change is tagging literals by **content**, not just by AST
position. This underpins enum/flag targets later.

### S0.1 — Add `LiteralSubkind` backed enum
- **Add** `src/ValueObject/LiteralSubkind.php`
  `enum LiteralSubkind: string` with cases
  `Unknown`, `Url`, `FilePath`, `Regex`, `Sql`, `Json`, `DateTimeFormat`,
  `Locale`, `Charset`, `MimeType`, `Version`, `HashAlgorithm`, `HttpMethod`,
  `HttpStatus`, `EventTag`, `ConfigKey`, `Bitmask`, `StatusCode`, `TimeUnit`,
  `Size`, `Port`. (Closed set → backed enum per ADR-0003.)
- **Tests:** mapping test — each case round-trips `from()/value`.

### S0.2 — Carry `subkind` on the literal carriers
- **Modify** `ValueObject/LiteralInterface.php` (add
  `public LiteralSubkind $subkind { get; }`), `StringLiteral.php`,
  `NumberLiteral.php`, and their constructors (default `LiteralSubkind::Unknown`).
  Keep the constructor signatures backward-compatible by giving `subkind` a
  default so existing factory/test calls still compile.
- **Tests:** a carrier built without `subkind` defaults to `Unknown`; built
  with a subkind exposes it.

### S0.3 — `ContentClassifier` port + adapter
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
- **Wire:** inject `ContentClassifierInterface` into `Adapter/MagicLiteralVisitor`
  (or compose it in `Adapter/Detector`) and set `$lit->subkind` during
  collection. Update `Factory/DetectorFactory::build*()` to construct and pass
  `ContentClassifier` (via a new `Factory/ContentClassifierFactory` or inline in
  `DetectorFactory`, which already lives in `Factory\` and may depend on
  `Adapter`).
- **Tests (fixture-based, adapter):** for a set of literal strings/numbers,
  assert the produced `subkind`. Port test: mock the interface, assert the
  visitor sets the subkind the classifier returns.

---

## Phase 1 — Scope & ignore controls (prerequisite for noisier kinds)

### S1.1 — `DisenchantScope` value object
- **Add** `ValueObject/DisenchantScope.php` `final readonly` implementing
  `ValueObject/DisenchantScopeInterface`, holding:
  - `list<string> $includePaths`, `list<string> $excludePaths` (glob),
  - `list<string> $includeNamespaces`, `list<string> $excludeNamespaces`,
  - `list<string> $excludeLiteralValues`,
  - `bool $ignoreTests = true` (drops `tests/`, `fixtures/`, `var/`).
  Provide a `fromConfig()` builder / default scope (exclude tests).

### S1.2 — `ScopeFilter` port + adapter
- **Add** `Contract/ScopeFilterInterface.php`
  `public function isAllowed(LiteralInterface $lit, string $filePath): bool;`
- **Add** `Adapter/ScopeFilter.php` `final readonly` implementing it (glob/
  namespace/literal matching, pure string ops).
- **Modify** `Adapter/CandidateFilter.php` to depend on
  `ScopeFilterInterface` and drop literals the scope rejects (after the existing
  role/kind checks).
- **Wire** via `Factory/DetectorFactory` (build `ScopeFilter` and pass into
  `CandidateFilter`).
- **Tests:** allow-list / deny-list / ignore-tests cases; idempotent.

---

## Phase 2 — Backed-enum promotion target

The README's "eventually backed enums". This is the biggest feature; build the
target seam first, then the enum strategy, then closed-set detection.

### S2.1 — `PromotionArtifact` carrier + target seam
- **Add** `ValueObject/PromotionArtifact.php` `final readonly` implementing
  `ValueObject/PromotionArtifactInterface`, describing an edit set:
  - `list<string> $insertions` (declaration source to inject),
  - `list<Replacement> $replacements` (line + old raw → `self::CONST` / `Enum::Case`),
  - a `TargetKind` (`Const` | `Enum` | `FlagSet` | `SharedConst`).
  Keep `ConstantPlacement` as one specialization or migrate it to produce a
  `PromotionArtifact` (prefer the latter so `LineBasedRewriter` has one shape).
- **Add** `Contract/PromotionTargetInterface.php`
  `public function supports(GroupedCandidate $group): bool;`
  `public function build(GroupedCandidate $group): PromotionArtifact;`
  (`GroupedCandidate` is the existing grouped literal set; reuse
  `CandidateInterface` grouping already produced by `CandidateGrouper`.)
- **Refactor** `Adapter/LineBasedRewriter` to consume `PromotionArtifact`
  instead of `ConstantPlacementInterface` directly (line-scoped insertion +
  replacement unchanged in behavior). Keep a compatibility shim so existing
  `ConstantPlacement` callers still work for one step, then remove it.

### S2.2 — `ClassConstantTarget` (extract current behavior)
- **Add** `Adapter/ClassConstantTarget.php` `final readonly` implementing
  `PromotionTargetInterface`; it emits exactly what `LineBasedRewriter` does
  today (the `ConstantDeclaration` VO, `self::CONST` replacement). This isolates
  today's logic behind the new seam; behavior must be **byte-identical** to
  current output (assert with a fixture diff test).
- **Wire** `CompositeConstantPlacementStrategy` (or a new
  `CompositePromotionTarget`) to consult `ClassConstantTarget` by default.

### S2.3 — Closed-set candidacy pass (feeds enum target)
- **Add** `Contract/ClosedSetDetectorInterface.php` +
  `Adapter/ClosedSetDetector.php`: given the grouped candidates, find groups
  where (a) all members share one `LiteralKind`, (b) they appear in the **same**
  variable/parameter/array-key context (use `NamingContext` + `LiteralRole`),
  and (c) the distinct-value count is small and stable → mark the group
  `isClosedSet = true` with a proposed enum name (derived from the naming
  context, e.g. `Status`).
- **Tests:** fixture where `$s` is always `'new'|'active'|'closed'` → flagged
  closed; a value used in wildly different contexts → not flagged.

### S2.4 — `BackedEnumTarget`
- **Add** `Adapter/BackedEnumTarget.php` implementing `PromotionTargetInterface`;
  when `supports()` (closed set of same-kind literals), it builds a
  `PromotionArtifact` that:
  - inserts `enum <Name>: <string|int> { case <Case> = '<raw>'; … }` in the
    chosen class (or a new file alongside, per scope),
  - replaces each literal with `<Name>::<Case>` (kebab/`Studly` case from the
    raw value).
- **Guard:** never promote a value already an enum backing
  (`LiteralRole::EnumBackingValue` is already excluded by `CandidateFilter`) —
  add a regression test that an existing `enum X: string { case A = 'a'; }`
  usage is left untouched.
- **Wire** `CompositePromotionTarget` to prefer `BackedEnumTarget` for closed
  sets, else `ClassConstantTarget`.
- **Tests:** full rewrite fixture (string set → enum + `X::Case` usages),
  idempotent on a second pass, no self-rewrap of the generated enum's own
  backing values.

---

## Phase 3 — Bitmask / flag-set target

### S3.1 — `Bitmask` sub-kind refinement
- **Extend** `Adapter/ContentClassifier` (S0.3): set `LiteralSubkind::Bitmask`
  for ints that are powers of two **and** sit in a `|`/`&` (`BinaryOp\BitwiseOr`/
  `BitwiseAnd`) or are grouped with other powers of two sharing a variable.
- **Tests:** `1`,`2`,`4`,`8` in bitwise context → `Bitmask`; `3` (not power of
  two) or a lone `100` → not `Bitmask`.

### S3.2 — `FlagSetTarget`
- **Add** `Adapter/FlagSetTarget.php` implementing `PromotionTargetInterface`;
  for a `Bitmask` group it emits:
  ```php
  public const int READ = 1;
  public const int WRITE = 2;
  public const int ALL = self::READ | self::WRITE;
  ```
  and rewrites `READ | WRITE` occurrences to `self::ALL` (or keeps the
  combination, configurable). Reuse `ConstantDeclaration` for each member.
- **Wire** into `CompositePromotionTarget` ahead of `ClassConstantTarget` for
  `Bitmask` sub-kind.
- **Tests:** bitwise literal set → flag const group; combined `|` replaced with
  `self::ALL`; idempotent.

---

## Phase 4 — Extend string coverage

### S4.1 — Heredoc / nowdoc (opt-in, size-gated)
- **Modify** `Adapter/MagicLiteralVisitor` to also enter `Scalar\Heredoc` /
  `Scalar\Encapsed` bodies; treat as `LiteralKind::String`, run them through
  `RoleClassifier` + `ContentClassifier` (SQL/JSON sub-kind), and **gate** by a
  max-body-size config on `DisenchantScope` (skip bodies > N chars by default).
- **Tests:** a repeated heredoc SQL body becomes `SQL_FIND_USER`; a 50 KB prose
  heredoc is skipped by default.

### S4.2 — FQCN strings: detect-and-exclude, optional `::class` converter
- **Modify** `Adapter/ContentClassifier` to mark `'App\\Foo\\Bar'`-shaped values
  `LiteralSubkind::Unknown` but set a `isClassnameString` flag on the carrier
  (new optional field) so `CandidateFilter` **excludes** them from generic
  promotion (they're references).
- **Add (explicit opt-in, separate adapter)** `Adapter/ClassnameToClassConst`
  behind its own `Contract/ClassnameToClassConstInterface`, converting
  non-concatenated, loadable FQCN strings to `<Short>::class`. Default **off**;
  only active when scope enables `convertFqcnToClassConst`.
- **Tests:** FQCN strings excluded by default; with the opt-in, a safe
  `'App\\Foo\\Bar'` → `Bar::class`; a concatenated `"App\\{$x}"` left alone.

---

## Phase 5 — Operational features

### S5.1 — Output / reporting formats
- **Add** `Adapter/JsonReportRecorder.php` and `Adapter/SarifRecorder.php`,
  both implementing the existing `Contract/ExecutionRecorderInterface`
  (ADR-0011). They turn the `disenchant.it.*.v1` occurrences DisenchantIt already
  emits into a JSON report / SARIF log. Wire as an alternative recorder in
  `Factory/DisenchanterFactory` (selected by a config flag).
- Add a `dryRun` mode to `LineBasedRewriter` (return the `PromotionArtifact`
  without applying) so `Disenchanter` can produce a diff/report without writing.
- **Tests:** recorder produces parseable JSON/SARIF from a known run.

### S5.2 — More syntactic positions
- **Extend** `Adapter/MagicLiteralVisitor` to collect literals in attribute
  arguments, property defaults, constant-initializer expressions, and `match`/
  `enum` arms (most roles already exist; add the node entries). Pure AST
  extension; same pipeline + tests (each new position yields a detected,
  promotable literal).

### S5.3 — Naming dictionary / policy
- **Add** `ValueObject/NamePolicy.php` (prefix/suffix/casing) +
  `Contract/NameResolverInterface` extension or a new
  `Contract/DictionaryNameResolverInterface` + `Adapter/DictionaryNameResolver`
  that maps a literal → explicit name from an optional dictionary file. Wire
  into `ConstantNameResolver`/`CompositeConstantPlacementStrategy`.
- **Tests:** dictionary override wins; policy casing applied.

### S5.4 — SemConv persistence
- **Add** `Contract/SemConvStoreInterface.php` + `Adapter/FileSemConvStore`
  (load/merge/save the canonical name→value map). `SemConvCollector` merges via
  the store when present; stable names across runs.
- **Tests:** second run reuses prior canonical names; merge preserves existing
  entries (no override of differing values — hard error per domain invariant).

### S5.5 — Post-rewrite verify observer (out-of-process)
- **Add** `Contract/VerificationObserverInterface.php` (one method
  `afterRewrite(string $rewrittenSource): void`) + `Adapter/PhpLintObserver`
  that shells out to `php -l` on a temp file (a **separate process**, never
  inside Core). Disenchanter invokes it through the execution recorder seam.
- **Tests:** observer invoked after rewrite; lint failure reported, original
  exception preserved.

---

## Phase 6 — Docs & cleanup

### S6.1 — Fix README drift
- `README.md` mentions `AutoConstantPlacementStrategy`; rename to the actual
  `CompositeConstantPlacementStrategy` (matches `src/`).

### S6.2 — New ADR for promotion targets
- Add `docs/ADR-0006-promotion-targets.md`: const vs backed-enum vs flag-set
  vs shared; the `PromotionTargetInterface` seam; closed-set detection policy.

### S6.3 — Update plan/roadmap status
- Mark delivered rows in `ADDITIONAL_FEATURES_PLAN.md` and this roadmap as done;
  keep rejected items (`true`/`false`/`null`, encapsed fragments, magic
  constants) explicitly out of scope.

---

## Recommended linear build order

```
S0.1 → S0.2 → S0.3          # sub-kind foundation (no behavior change yet)
S1.1 → S1.2                 # scope/ignore (safe to land early)
S2.1 → S2.2 → S2.3 → S2.4   # backed-enum target (biggest feature)
S3.1 → S3.2                 # bitmask/flags
S4.1 → S4.2                 # string coverage extension
S5.1 → S5.2 → S5.3 → S5.4 → S5.5   # operational
S6.1 → S6.2 → S6.3         # docs
```

Each arrow is a shippable PR that keeps `composer architecture`, `composer
analyze`, `composer format:check`, and `composer test:unit` green.

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
