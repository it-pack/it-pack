# DisenchantIt — In-Depth Engineering Review

> Scope: the `src/` library after Phases 0–6 (≈167 files; 164 unit tests / 378
> assertions). Covers **Features, Architecture (hexagonal / ports & adapters),
> SOLID, DRY, Modern PHP leverage, Performance, Security, Maintainability,
> Developer Experience, and interface-coverage / complexity / anti-pattern
> checks**.
>
> Method: source read of the orchestrator, rewriter, promotion targets, cache,
> observer, store, factories, and representative value objects; plus a run of the
> repository quality gates (`composer architecture`, `vendor/bin/phpstan analyse
> src --level=9`, `composer format:check`, `vendor/bin/codecept run unit`). No
> target project was scanned; runtime claims are derived from the implementation
> and its runnable tests.
>
> This review supersedes the earlier `docs/archive/REVIEW_1.md` (written before
> Phases 0–6) and reflects the current codebase. It is intentionally
> review-ready: every finding cites `file:line`.

---

## 0. Review checklist (conceptual)

- **Fitness of features** — does the library deliver its stated mission (magic
  scalars → named constants) across strings, numbers, enums, flags, SemConv, and
  out-of-process verification, without violating the source-text safety rule?
- **Architectural integrity** — ports/adapters layering, dependency direction,
  factory discipline, and whether every concrete class is backed by a port.
- **Design-principle health** — SOLID and DRY in practice, complexity, and the
  presence of the requested anti-patterns (`else`/`elseif`, magic strings/numbers).
- **Modern-PHP leverage** — where `readonly`, enums, property hooks, traits,
  WeakMap/WeakReference, lazy objects, and Fibers are (or could be) used well.
- **Non-functional quality** — performance envelope, security boundary,
  maintainability, and developer experience for consumers and contributors.

---

## 1. Scorecard (condensed)

| Dimension | Rating | One-line rationale |
|---|---|---|
| Features | **Strong** | Strings + numbers (typed), enums, flag-sets, SemConv registry, dry-run, SARIF/JSON, `php -l` verify. |
| Architecture (hexagonal) | **Strong** | Clean Contract/Core/Adapter/ValueObject/Factory split; phparkitect enforces it (gate PASS). |
| SOLID | **Strong** | SRP/ISP/OCP well applied; DIP via ports throughout; one seam (`PromotionTarget`) is exemplary. |
| DRY | **Good** | A few concrete duplications (name-slugify, const-regex, class-brace scan) flagged in §8. |
| Modern PHP | **Strong (selective)** | `readonly` (51 classes) + enums + property hooks + traits + lazy (generator) used well; WeakMap/Fibers unused (justified). |
| Performance | **Good** | Content-keyed parse cache avoids 3× parse; flag cache unbounded across many files (see §8). |
| Security | **Strong** | Hard "never execute/autoload" rule upheld; out-of-process `php -l` with `escapeshellarg`; fail-closed store. |
| Maintainability | **Strong** | Small focused classes, consistent factory pattern, green gates, tests per behavior. |
| DX | **Strong** | Single `DisenchanterFactory` entry point, optional ports, clear `USER_GUIDE.md`, no required config. |

Overall: a **mature, well-shaped library**. The findings are about consistency and
a few high-value, low-risk consolidations — nothing is broken or architecturally
at risk.

---

## 2. Features

DisenchantIt promotes magic scalar values (string **and** numeric literals) found
in PHP source into explicit declarations and, optionally, a shared SemConv
registry — **without ever executing or autoloading the analysed file** (the hard
rule, enforced at `Core/Disenchanter.php:39-41` and `Adapter/PhpLintObserver.php`
which only *lints*, never runs, the source).

Delivered capability set (verified against `src/` and `tests/Unit`):

- **String detection** by AST role (`MagicLiteralVisitor`), identifier-shape
  filtered, grouped by value, slugified (`RoleClassifier`, `CandidateFilter`,
  `CandidateGrouper`, `ConstantNameGenerator`).
- **Numeric detection** with naming-hint recovery and **typed** constants that
  preserve the raw token (`0x1F` stays `0x1F`); trivial values and hint-less
  numbers excluded (`ContentClassifier.php:106` power-of-two check,
  `NamingContextExtractor.php`).
- **Promotion targets** behind one seam: `ClassConstantTarget` (const fallback),
  `BackedEnumTarget` (closed sets), `FlagSetTarget` (bitmask sets),
  `ClassnameToClassConst` (opt-in `::class`); composed first-match-wins in
  `PromotionTargetFactory::build()` / `CompositePromotionTarget` (ADR-0006).
- **SemConv** collect + alias-through (`SemConvCollector`,
  `SemConvAliasRewriter`), now persisted across runs via `FileSemConvStore`
  (Phase 5, `FileSemConvStore.php`).
- **Idempotency** by construction — const initializers excluded
  (`MagicLiteralVisitor::isInsideClassConstDeclaration`), enum backing values
  filtered, already-rewritten references not re-matched.
- **Operational**: JSON/SARIF recorders (Phase 5), `dryRun` planning
  (`Disenchanter.php:109-113`), and a post-rewrite `php -l` observer
  (`PhpLintObserver.php`) invoked only after a successful rewrite so the original
  business exception is preserved (`Disenchanter.php:164-167`).

No feature gaps were found relative to the stated mission.

---

## 3. Architecture — hexagonal (ports & adapters)

The layered split is real and mechanically enforced:

- `Contract` depends only on `ValueObject` (enums/interfaces) — verified by the
  phparkitect gate (`composer architecture` → **PASS**).
- `Core` (`Disenchanter`) depends only on ports + value objects
  (`Core/Disenchanter.php:7-29`); it imports **no** adapter and **no**
  `ItPack\RunIt\*` (ADR-0001/0005).
- `Adapter` depends on `Contract` + `ValueObject` (+ vendor types, e.g. nikic
  AST) — never on `Core`.
- `Factory` is the composition root and is the only layer allowed to wire
  adapters (`phparkitect.php` `Factory` may depend on `Adapter`, `Core`).

**Interface backing (explicit check).** Every `final` class is backed by a port.
The name-mismatch heuristic (e.g. `BackedEnumTarget` ↔ `PromotionTargetInterface`,
`DisenchantOccurrence` ↔ `ExecutionArtifactInterface`,
`VerificationException` ↔ `DisenchantItExceptionInterface`) all resolve to a real
interface; the phparkitect rule `final classes must be backed by an interface`
(ADR-0004) enforces this and passes. **No concrete class lacks a backing
interface.** No abstract classes exist in `src/`.

**Factory discipline (explicit check).** All 9 factories follow the AGENTS.md
pattern: a `static function create(...): self` proxy plus an instance
`build()` that returns the product (`Factory/*.php`, e.g.
`DisenchanterFactory.php:50,72`). No inline `new` of collaborators inside domain
code was observed.

**OCP seam (exemplary).** Adding a promotion shape is additive: implement
`PromotionTargetInterface` and register it in `PromotionTargetFactory::build()` —
`Core\Disenchanter` is untouched (ADR-0006 §2). This is the cleanest extension
point in the codebase.

---

## 4. SOLID

- **SRP** — classes are small and single-purpose (e.g. `ClosedSetDetector`,
  `RoleClassifier`, `ScopeFilter`, `ParsedSourceCache`). `LineBasedRewriter` is
  the largest at 359 lines but is one cohesive responsibility (apply artifacts to
  lines); see §8 for internal DRY nits.
- **OCP** — `CompositePromotionTarget` + port registry (§3); `CandidateFilter`
  chain; recorder ports.
- **LSP** — ports have clear substitution semantics; `NullExecutionRecorder`
  (`Adapter/NullExecutionRecorder.php`) is a faithful no-op substitute.
- **ISP** — interfaces are narrow and focused (e.g. `RewriterInterface`,
  `VerificationObserverInterface`, `SemConvStoreInterface`). No "fat" interface
  was found.
- **DIP** — `Core` depends on abstractions only; adapters depend on
  abstractions; wiring happens in `Factory`. Confirmed.

SOLID is **strong**. The only nuance: value objects such as `SemConvConfig` mix
config data with a computed virtual property (`outputPath`) — acceptable and
intentional (ADR-0003/property-hook guidance), not a violation.

---

## 5. DRY — findings

DRY is **good** with a few concrete, low-risk consolidation opportunities:

1. **Name-slugify duplication (medium).** `BackedEnumTarget::pascalCase`
   (`Adapter/BackedEnumTarget.php:102`) and `FlagSetTarget::pascalCase`
   (`Adapter/FlagSetTarget.php:151`) are near-identical. The author even notes
   "mirrors BackedEnumTarget's case naming, kept private to avoid a shared
   dependency" (`FlagSetTarget.php:~149`). Recommend extracting a single
   `NameSlugifier` (value object or stateless service) used by both — removes the
   parallel-maintenance risk without coupling the two targets.
2. **Const-detection regex duplicated (low).** The identical pattern
   `/\bconst\s+(?:[A-Za-z_][A-Za-z0-9_]*\s+)?([A-Z_][A-Z0-9_]*)\s*=/` appears at
   `LineBasedRewriter.php:205` and `:288`. Extract a private
   `constNameFromLine()` helper.
3. **Class-brace scan duplicated (low).** `findClassClosingBraceLine`
   (`:307`) and `findClassOpeningBraceLine` (`:338`) share the declaration-line
   search + brace-depth loop; only the return condition differs. A single
   `findClassBraceLine(className, which: 'open'|'close')` (or returning both
   indices) removes the near-duplicate. Note this would reintroduce a small
   branch — acceptable given the removed duplication, or keep both as thin
   wrappers over one scanner.

None of these affect behavior; all are refactors safe under the existing tests.

---

## 6. Anti-pattern checks (`else` / magic strings / numbers)

**`else` / `elseif` (explicit check).** Only 5 occurrences in `src/`, all
defensible:

- `LineBasedRewriter.php:47` — `instanceof` dispatch (`PromotionArtifactInterface`
  else `ConstantPlacementInterface`); could become a `match`, low priority.
- `LineBasedRewriter.php:194` — `if ($topLevel) {…} else {…}`; flip to early
  `continue` to drop the `else`.
- `FlagSetTarget.php:139` — `elseif` in an accumulator loop (first vs differing
  variable); readable as-is.
- `ClassConstantTarget.php:70,94` — `if ($isMultiline) {…} else {…}` pairs (raw vs
  needle, span vs single). Natural; early-return style optional.

Recommendation: convert the trivial `else` sites (`:194`, optionally `:70/:94`)
to guard/early-return for consistency with the repo's "avoid else" guidance, but
this is cosmetic, not a defect.

**Magic strings (explicit check).**

- Visibility `'private'` appears at `LineBasedRewriter.php:145` and
  `ClassConstantTarget.php:85`. Minor; consider a `Visibility` enum or a
  `ConstantDeclaration::VISIBILITY_PRIVATE` constant to remove the literal.
- Regex literals (above) and the `(?:class|enum|interface|trait)` alternation
  (`LineBasedRewriter.php:310,341`) are duplicated; extract as named constants.

**Magic numbers (explicit check).** No egregious magic numbers. `4096`
heredoc cap is a named constant `DisenchantScope::DEFAULT_MAX_HEREDOC_BODY_CHARS`
(`ValueObject/DisenchantScope.php:50`); `json_decode(..., 512, …)`
(`DictionaryNameResolver.php:48`) is the standard default depth; the
power-of-two check `($value & ($value - 1)) === 0` (`ContentClassifier.php:106`)
is inherent. Clean.

---

## 7. Modern PHP — current use and leverage

**Currently used well:**

- **`readonly`** — 51 `final readonly` classes (value objects, ports, factories);
  immutability underpins the source-text-safety and idempotency guarantees.
- **Enums (backed)** — `LiteralKind`, `LiteralRole`, `LiteralSubkind`,
  `TargetKind`, `NameCasing` are closed internal state (ADR-0003). Correct.
- **Property hooks / virtual properties** — `SemConvConfig::$outputPath` uses a
  `get` hook (`ValueObject/SemConvConfig.php:39`); `SemConvConfig` also uses
  promoted `readonly` constructor properties. This is the recommended pattern and
  is applied exactly where it earns its keep (a derived, config-overridable path).
- **Traits (focused)** — `LiteralValueObjectTrait` (shared `needle()` on
  `StringLiteral`/`NumberLiteral`) and `NamespaceUseInserterTrait` (used by
  `SemConvAliasRewriter`). Both are small, interface-harmonizing, and free of
  hidden state — consistent with AGENTS.md.
- **Lazy (generator)** — `LiteralDetectorInterface::scanSourceLazy`
  (`Contract/LiteralDetectorInterface.php:29`) yields literals one at a time; the
  memory-friendly variant exists even if the default path uses the array-returning
  `scanSource`.

**Opportunities (not required — leverage only):**

- **WeakMap** — the cache *rationale* mentions WeakMap, but `ParsedSourceCache`
  deliberately uses a content-hash-keyed plain array (`Adapter/ParsedSourceCache.php:28`)
  because `SourceCode` objects are recreated per call (a WeakMap-by-object would
  miss cross-call). The trade-off is documented and correct. However, the array is
  **unbounded** (see §8/§9) — for a long-lived `Detector` scanning many distinct
  sources this grows without bound, against AGENTS.md's "bounded buffers" rule. A
  real `WeakMap` won't fit the cross-call need; a bounded LRU/size-capped map
  keyed by `SourceCode::identity()` would.
- **Fibers** — unused. AGENTS.md suggests Fibers to overlap I/O and parsing
  across a multi-file tree. The current `Disenchanter` is single-source; if a
  batch/multi-file driver is added, Fibers (or a simple parallel scan) are a
  legitimate leverage point. Not warranted today.
- **Lazy objects (Ghost)** — the parser stack is already built once in
  `NikicParserFactory` and reused, so a lazy/Ghost parser is unnecessary now.
- **Property hooks** — could extend to other derived fields (e.g. a `Candidate`'s
  display name, `GroupedCandidate` derived stats), but current getters are fine;
  only adopt where it removes duplication or clarifies intent.

---

## 8. Performance

- **Parse once, not three times.** `ParsedSourceCache` collapses the
  `detect → rewrite → rewriteWithSemConv` triple-parse into one per distinct
  source via content-hash identity (`SourceCode::identity()`, `xxh128`). Good.
- **No reflection on hot paths**, no filesystem in Core, no network — all per
  AGENTS.md.
- **WeakMap/`WeakReference`**: see §7 — the content-keyed cache is the right call,
  but it is **unbounded** (`Adapter/ParsedSourceCache.php:28`). For a single
  `Disenchanter` over one file this is moot; for a reused `Detector` over a large
  tree it is a memory-growth risk. **Recommendation:** cap the store (e.g. LRU or
  a max-entries bound) or document the intended single-use-per-Detector lifetime
  as a contract.
- **Line-based rewrite** preserves untouched lines and applies insertions
  bottom-up (no re-parse) — O(lines) with a couple of `usort` passes;
  appropriate.

No benchmarks were run (none shipped); claims are structural.

---

## 9. Security

- **Source-text safety (the hard rule) is upheld.** Core never
  requires/includes/autoloads/executes analysed code; `PhpLintObserver` only runs
  `php -l` (syntax check, not execution) and on a throwaway temp file.
- **No command injection.** `PhpLintObserver::afterRewrite`
  (`Adapter/PhpLintObserver.php:51-54`) `escapeshellarg`s both the PHP binary
  (default `PHP_BINARY`) and the temp path; the source content is written to the
  file, never interpolated into the command. Temp file is removed in `finally`.
- **Fail-closed persistence.** `FileSemConvStore::save`
  (`Adapter/FileSemConvStore.php:54-67`) throws on any write failure rather than
  silently losing the accumulated registry; path is caller-supplied config
  (composition root), not user-controlled input.
- **Dictionary load** (`DictionaryNameResolver::fromFile`,
  `Adapter/DictionaryNameResolver.php:48`) uses `JSON_THROW_ON_ERROR`; file path
  is trusted config.
- **No secret exposure**: analysed source and SemConv registry are written as-is;
  no redaction needed because nothing sensitive is synthesized. (AGENTS.md's
  redaction guidance targets observations/occurrences published externally — the
  recorders currently emit structural counts/payloads, not source bodies; if
  future recorders embed source excerpts, apply classification/redaction then.)

Security is **strong**; no vulnerabilities found.

---

## 10. Maintainability & DX

- **Consistent conventions**: `declare(strict_types=1)`, `final readonly`,
  narrow interfaces, factory `build()`/`create()`, exception marker
  (`DisenchantItExceptionInterface`). Green gates make regressions visible.
- **Tests**: 164 unit tests / 378 assertions covering invariants (idempotency,
  self-rewrap avoidance, enum-backing exclusion, SemConv collision hard-error,
  dry-run, observer preservation). Strong per-behavior coverage.
- **Documentation**: `USER_GUIDE.md` is current and matches `src/` (Phase 6 fixed
  the `CompositeConstantPlacementStrategy` drift); ADRs under
  `docs/architecture/decisions/` are referenced from code. `EXECUTION.md`
  (kept live) defines the execution model.
- **DX for consumers**: one `DisenchanterFactory::create()` entry point; all
  advanced behavior is opt-in via ports (`recorder`, `observer`, `semconvStore`,
  `namePolicy`, `dictionary`, `convertFqcnToClassConst`). No mandatory config.
- **Minor maintainability nits** (non-blocking): the docblock stack at
  `Core/Disenchanter.php:59-66` has a stray repeated `/**`; the legacy
  `ConstantPlacementInterface` shim in `LineBasedRewriter::artifactFromPlacement`
  (`:135`) is explicitly marked "REMOVE once all callers emit PromotionArtifacts"
  — track its removal.

---

## 11. Findings summary

| # | Area | Severity | Location | Recommendation | Status |
|---|------|----------|----------|----------------|--------|
| F1 | DRY | Medium | `BackedEnumTarget.php:102` / `FlagSetTarget.php:151` | Extract shared `NameSlugifier`. | ✅ Resolved (M1 — `Adapter\NameSlugifier`) |
| F2 | DRY | Low | `LineBasedRewriter.php:205,288` | Extract `constNameFromLine()` helper. | Open (M3) |
| F3 | DRY | Low | `LineBasedRewriter.php:307,338` | Unify class-brace scan into one scanner. | Open (M3) |
| F4 | Perf / maint | Low–Med | `ParsedSourceCache.php:28` | Bound the content-keyed cache (LRU/size cap) or document single-Detector lifetime. | Open (M2) |
| F5 | Style | Low | `LineBasedRewriter.php:47,194`; `ClassConstantTarget.php:70,94`; `FlagSetTarget.php:139` | Convert trivial `else` to early-return/guard. | Open (M5; `FlagSetTarget.php:139` won't-fix) |
| F6 | Magic string | Low | `LineBasedRewriter.php:145`; `ClassConstantTarget.php:85` | Use a `Visibility` constant/enum. | Open (M5) |
| F7 | Maint | Low | `LineBasedRewriter.php:135` | Remove legacy `ConstantPlacementInterface` shim when safe. | Open (M4) |
| F8 | DX | Info | `Core/Disenchanter.php:59-66` | Fix duplicate docblock. | Open (M4) |

No architectural, security, or SOLID defects were found.

---

## 12. Conclusion

DisenchantIt is a **well-architected, disciplined library** that honors its
hard source-text-safety rule while delivering a broad, coherent feature set. The
hexagonal split is not decorative — it is mechanically enforced by phparkitect
and every concrete class is port-backed; the `PromotionTargetInterface` seam is a
model OCP extension point. Modern PHP (`readonly`, enums, property hooks, traits,
lazy generators) is used where it earns its keep.

The review's actionable items are **consistency and low-risk consolidation**
(F1–F8), chiefly the duplicated name-slugify logic and the unbounded parse cache.
None block delivery; all are safely refactorable under the existing test suite.
Recommended next step: land F1 (shared slugifier) and F4 (bounded cache), then
sweep F2/F3/F5/F6 as a single style pass.

---

## Appendix — verification commands run

```
composer architecture                              # PASS (no violations)
vendor/bin/phpstan analyse src --level=9           # no errors
composer format:check                              # 0 files to fix
vendor/bin/codecept run unit                       # OK (164 tests, 378 assertions)
```

## Assumptions

- "Review-ready" = a structured report with `file:line` references, mirroring the
  prior `docs/archive/REVIEW_1.md` style, placed at `docs/REVIEW.md` (live).
- Prior roadmap/plan docs were moved to `docs/archive/`; this review reflects the
  current `src/` (post Phases 0–6) and supersedes `REVIEW_1.md`.
- "Leverage of modern PHP" is assessed as **opportunities**, not required changes;
  the codebase already uses the relevant features appropriately.
- No source files were modified; this is a review document only.
