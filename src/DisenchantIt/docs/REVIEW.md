# DisenchantIt — In-Depth Engineering Review (post-consolidation)

> Scope: the `src/` library after the M0–M6 refactoring plan completed (135 source
> files; 172 unit tests / 431 assertions). Covers **features, architecture,
> maintenance, performance, maintainability, security, DX, SOLID, DRY, hexagonal
> architecture (ports/adapters/factories), modern-PHP leverage, interface backing,
> code organization, and test quality**.
>
> Method: full-tree structural scans (type-per-file, abstract-class, modifier,
> token-level declaration-order analysis), targeted anti-pattern greps
> (`else`/`elseif`, Yoda conditions, magic literals, >120-char lines), instance-
> creation mapping, constant-vocabulary hosting audit, pcov-measured unit
> coverage, and a run of all repository gates. Every finding cites `file:line`.
>
> This document supersedes `docs/archive/REVIEW_2.md` (pre-consolidation) and
> reflects the current tree.

---

## 0. Review checklist (conceptual)

- **Fitness & architecture** — mission delivery, hexagonal layering and dependency
  direction, factory discipline, every concrete class backed by a port, zero
  abstract classes.
- **Design-principle health** — SOLID, DRY, low complexity, absence of
  `else`/`elseif`, magic strings/numbers, and Yoda conditions; vocabularies in
  interfaces/enums.
- **Modern-PHP leverage** — readonly classes, property hooks/virtual properties,
  traits, WeakMap/WeakReference, lazy objects, Fibers.
- **Code organization** — PSR-4 one-type-per-file, complete imports, instances
  created only in factories/builders via DI, declaration-order convention,
  ≤120-char lines.
- **Non-functional quality** — performance envelope, security boundary,
  maintainability, developer experience.
- **Test quality** — measured coverage against the 95% bar, cross-test leakage
  exposure, mutation-testing leverage.

---

## 1. Scorecard (condensed)

| Dimension | Rating | One-line rationale |
|---|---|---|
| Features | **Strong** | Strings + typed numbers, enums, flag sets, SemConv persistence, dry-run, SARIF/JSON, out-of-process verify — unchanged since M6 closeout. |
| Architecture (hexagonal) | **Strong** | Layered rules enforced by phparkitect (PASS); Contract→ValueObject only; Core vendor-free; Factory composition root. |
| Interface backing | **Strong** | All 69 concrete classes port-backed; phparkitect rule passes; zero abstract classes. |
| SOLID | **Strong** | SRP/ISP/OCP hold; the PromotionTarget seam remains exemplary. |
| DRY | **Strong** | Post-M3/M5 consolidation: single-source regexes/patterns, one slugifier, one Visibility enum; no residual duplication found beyond intentional trait reuse. |
| Code organization | **Good** | One type per file everywhere; imports complete; **but** declaration-order deviations exist in 13+ files (§7) and 9 code lines exceed 120 chars (§8). |
| Modern PHP | **Strong (selective)** | readonly ×52, hooks, traits, generator-lazy scanning; WeakMap/Fibers deliberately unused with documented rationale. |
| Performance | **Good** | Bounded content-keyed parse cache (cap 32), parser reuse, O(lines) rewriter. |
| Security | **Strong** | Source-text hard rule intact; `exec()` fully `escapeshellarg`'d; fail-closed store. |
| Maintainability | **Good** | Small cohesive classes, green gates; ordering inconsistencies are mechanical to fix. |
| DX | **Strong** | Single entry point, opt-in ports, current USER_GUIDE, examples smoke-run clean. |
| Test quality | **Needs work** | Measured **90.00%** line coverage vs the 95% target (18 files below); mutation testing absent. |

Overall: the consolidation plan landed cleanly — architecture and style discipline
are excellent. The gap between current state and this review's bar is concentrated
in **test depth (coverage + mutation)** and **mechanical ordering/formatting
conventions**, all addressable without behavioral risk.

---

## 2. Features

Capability set verified end-to-end by the suite: string detection with role
classification and identifier filtering; numeric detection with naming-hint
recovery producing raw-token-preserving typed constants; promotion targets
(const / backed enum / flag set / `::class`) behind one composite seam; SemConv
collect + alias-through with persistent fail-closed store; dry-run planning;
JSON/SARIF recorders; post-rewrite `php -l` observation. Idempotency is asserted
by second-pass identity tests across suites. No feature gaps relative to the
stated mission were found.

## 3. Architecture — hexagonal, ports & adapters, factories

- **Layering is mechanically enforced** (`composer architecture` → PASS):
  Contract may depend on ValueObject only; Core on Contract+ValueObject; Adapter
  on Contract+ValueObject(+Adapter); Factory wires everything. No
  `ItPack\RunIt\*` anywhere (ADR-0005 holds).
- **Zero abstract classes** — verified by scan; shared behavior travels through
  two focused traits instead (`LiteralValueObjectTrait`, used by StringLiteral +
  NumberLiteral; `NamespaceUseInserterTrait`, used by SemConvAliasRewriter),
  both stateless and interface-aligned per AGENTS.md.
- **Every concrete class is backed by an interface**: 52 `final readonly` + 17
  plain `final` classes each `implements …Interface` (exceptions implement the
  `DisenchantItExceptionInterface` marker; phparkitect's backing rule passes).
  Plain-`final` classes are exactly the stateful ones (Detector, cache, stores,
  recorders, visitor, rewriter, two legacy placement strategies) — `readonly`
  where mutation-free is applied correctly.
- **Factory contract (ADR-0002)** holds: composition happens only in `Factory\`;
  domain code receives collaborators via constructor injection. One deviation
  found — see Finding F5 (inline collaborator default in `MagicLiteralVisitor`).
- **Instance-creation map**: outside `Factory\`, every `new` instantiates
  *immutable value objects* (`StringLiteral`, `NumberLiteral`, `Candidate`,
  `GroupedCandidate`, `SourceCode`, `ConstantDeclaration`, `Insertion`,
  `Replacement`, `PromotionArtifact`, `DisenchantOccurrence`) — data carriers,
  consistent with repo precedent (and with `ConstantPlacementFactory` reserved
  for the one VO that participates in strategy selection). No service/collaborator
  is constructed in Core or Adapter other than F5.

## 4. Design principles — SOLID, DRY, complexity

- **SOLID**: SRP holds (largest file is the 355-line single-purpose
  `LineBasedRewriter`); OCP via composite targets/filters; ISP — no fat ports
  found; DIP total (Core imports no adapters/vendor types in signatures).
- **DRY**: the earlier duplications are gone (single slugifier, single const-name
  regex, single declaration-pattern builder, unified brace scanner, Visibility
  enum). Remaining repetition is idiomatic VO assembly inside targets, which
  reads clearer than an abstraction would.
- **Complexity**: guard-clause style throughout; `else`/`elseif` count in `src/`
  is **zero**; nesting stays shallow; the rewriter's delta-tracking loop is the
  most intricate logic and is covered by span-edit fixtures.
- **Yoda conditions**: corrected-pattern scan found **none**
  (`literal === variable` forms are absent; comparisons uniformly place the
  variable left).

## 5. Modern PHP — leverage audit

| Feature | Status | Assessment |
|---|---|---|
| `readonly` classes | 52 classes | Backbone of immutability guarantees. |
| Enums (backed) | LiteralKind/Role/Subkind, TargetKind, NameCasing, **Visibility** | Closed internal vocabularies done right. |
| Property hooks / virtual props | All `…Interface` ports declare `{ get; }`; `SemConvConfig::$outputPath` virtual | Ports-as-hooks pattern is consistent and enforced. |
| Traits | 2, both stateless | Correct alternative to abstract bases (zero abstract classes exist). |
| Lazy objects | Generator-based streaming (`scanSourceLazy`) + manual first-use parser init | Right-sized; Ghost/proxy machinery would add risk without need. |
| WeakMap / WeakReference | Unused (docblock-only mentions) | **Correct**: object identity churns per call; the bounded content-keyed cache is the documented replacement (M2). |
| Fibers | Unused | Justified until a multi-file driver exists; revisit then. |

No misuse found; opportunities remain intentionally closed with rationale.

## 6. Constants, enums & vocabulary hosting

- Shared cross-cutting vocabulary lives correctly in the `SemConv` interface
  (`NAMESPACE_SEPARATOR`, `LINEBREAK`, `CONSTANT_SEPARATOR`) and in backed enums.
- Internal tuning knobs are `private const` on their classes — fine as
  implementation detail.
- **Deviation from the vocabulary rule:** four *public* tunables are hosted on
  concrete classes rather than their ports:
  `Detector::MAX_SOURCE_BYTES` (Adapter/Detector.php:61),
  `ParsedSourceCache::DEFAULT_MAX_ENTRIES` (:30),
  `SemConvConfig::DEFAULT_INTERFACE_NAME/_NAMESPACE` (:39–40), and
  `DisenchantScope::DEFAULT_MAX_HEREDOC_BODY_CHARS` (:50). Consumers referencing
  defaults bind to implementations, weakening substitutability (Finding F3).

## 7. Code organization

### 7.1 Clean
- **One declared type per file** across all 135 src files (PSR-4 ✓).
- **Imports complete**: phpstan lvl 9 clean, LSP clean, no unqualified
  cross-namespace references, no stray backslash-FQCNs in code bodies. Global PHP
  classes follow the uniform leading-backslash convention (`\RuntimeException`,
  `\Throwable`, `\InvalidArgumentException`); the single docblock FQCN
  (`\PhpParser\Parser` in Detector) is a deliberate, documented vendor-type
  isolation.

### 7.2 Findings — declaration order
Token-level analysis (after suppressing method-signature/body noise) plus manual
verification of every hit yields these genuine deviations from the prescribed
order *(trait use → constants → properties/hooks → constructor → factory methods →
non-static methods → static methods; public-first within non-static)*:

| # | Location | Deviation |
|---|---|---|
| a | `ConstantNameGenerator.php:36`, `ConstantNameResolver.php:53`, `DictionaryNameResolver.php:37`, `NumericNameGenerator.php:70`, `NikicParserFactory.php:18`, `NikicTraverserFactory.php:23`, `ConstantPlacementFactory.php:23`, `MagicStringVisitorFactory.php:31`, `SemConvConfigFactory.php:25` | `static create()` placed **after** instance methods — systematic; contradicts the prescribed factory-before-instance order while matching the repo's ergonomic convention (ADR-0002 examples show `create()` first, but the shipped files drifted). Decision required: either reorder or amend the convention text. |
| b | `Core/Disenchanter.php:66` | Private `record()` precedes the public `detect()/rewrite()/rewriteWithSemConv()` trio. |
| c | `ContentClassifier.php:113`, `MagicLiteralVisitor.php:247` | Public methods (`isClassNameString`, `getFound`) appear after private helpers. |
| d | `Detector.php:61`, `DisenchantScope.php:50` | Class constants declared after properties/promoted-constructor properties. |
| e | `ValueObject/ConstantPlacementInterface.php:35` | Hooked property `$occurrences` declared after method `typeKeyword()`. |

### 7.3 Findings — line length
Nine code lines exceed 120 characters (generated `tests/_support/_generated/*`
excluded): `Contract/DisenchanterInterface.php:41,57` and
`Core/Disenchanter.php:101,122` (long signatures — parameters belong one per
line here), `Adapter/LineBasedRewriter.php:96`, `Adapter/ScopeFilter.php:42`,
`Adapter/NamingContextExtractor.php:113`, `Factory/MagicStringVisitorFactory.php:28`,
`Exception/InvalidCacheConfigurationException.php:16` (extends/implements chain).

### 7.4 Magic literals
None outstanding: heredoc cap is `DisenchantScope::DEFAULT_MAX_HEREDOC_BODY_CHARS`,
trivial numerics are `CandidateFilter::TRIVIAL_NUMERIC_VALUES`, JSON depth `512`
is the standard default, power-of-two bit-trick inherent, closed-set bounds are
named (`MIN_DISTINCT`/`MAX_DISTINCT`). The one inconsistency: 
`ParsedSourceCache::DEFAULT_MAX_ENTRIES` omits its scalar type (`const int`),
unlike every other typed constant.

## 8. Performance

Parse-once via the bounded content-keyed cache (FIFO cap 32) — memory now
proportional to the cap even for reused Detectors over large trees; parser built
once per Detector (fixture-counted); rewriter preserves untouched lines and
inserts bottom-up; streaming variant exists for memory-sensitive scans. No hot-path
reflection, no filesystem/network in Core. Claims are structural; benchmarks
remain unwritten (unchanged from prior review).

## 9. Security

Hard rule intact — analysed sources are never executed/autoloaded; detection and
rewrite are pure text transforms. The one process escape, `PhpLintObserver::afterRewrite`,
runs `php -l` with **both** binary and temp path passed through
`escapeshellarg` (src/Adapter/PhpLintObserver.php:38-40) and cleans up in
`finally`. `FileSemConvStore` fails closed on write errors; recorder payloads
carry counts, not source bodies. No new surface since M2 added the
configuration-validation exception (also fail-closed).

## 10. Developer experience

Single `DisenchanterFactory::create()->build()` entry point; everything advanced
opt-in through ports; USER_GUIDE matches the API (no drift found for the touched
surfaces); examples 05/14 execute cleanly; composer scripts cover
test/phpstan/architecture/style. Upgrade notes for the three pre-1.0 API changes
live in the archived plan; consider promoting them to a changelog when releases start.

## 11. Test quality

- **Measured coverage (pcov, unit suite): 90.00% lines (1089/1210)** — below the
  95% requirement. Eighteen files fall short; the material gaps:

  | File | Lines |
  |---|---|
  | `Adapter/DictionaryNameResolver.php` | 23.1% (3/13) |
  | `ValueObject/ConstantPlacement.php` | 50.0% (1/2) |
  | `Adapter/RoleClassifier.php` | 60.0% (21/35) |
  | `Adapter/FileSemConvStore.php` | 71.4% (10/14) |
  | `Adapter/LineBasedRewriter.php` | 71.4% (90/126) |
  | `Adapter/SemConvConfig.php` | 75.0% (3/4) |
  | `Adapter/NamingContextExtractor.php` | 76.4% (42/55) |
  | `Adapter/NamespaceUseInserterTrait.php` | 81.3% |
  | `Adapter/ClassnameToClassConst.php` | 82.4% |
  | `Adapter/JsonReportRecorder.php` | 83.3% |
  | `Adapter/BackedEnumTarget.php` | 86.7% |
  | `Adapter/SemConvAliasRewriter.php` | 87.5% |
  | `Adapter/CompositePromotionTarget.php` | 88.9% |
  | `Adapter/PhpLintObserver.php` | 88.9% |
  | `ValueObject/DisenchantScope.php` | 90.2% |
  | `Adapter/SarifRecorder.php` | 93.5% |
  | `Adapter/ContentClassifier.php` | 94.4% |
  | `Adapter/FlagSetTarget.php` | 94.7% |

  Priority targets: RoleClassifier (role taxonomy edges), LineBasedRewriter
  (span edits/stale-line defenses — the most safety-critical logic),
  NamingContextExtractor, DictionaryNameResolver (error paths).
- **Mutation testing: not leveraged.** No Infection binary or script exists.
  AGENTS.md names it a priority area (placement decisions, idempotency guards,
  adapter mappings) — those are exactly today's weakest-covered spots.
- **Cross-test leakage**: the coverage XML is aggregate; per-test attribution is
  not produced by the current toolchain, so leakage cannot be *measured* today.
  Structurally, unit suites target single classes with hand-built fixtures; the
  known overlap is intentional orchestration coverage (DisenchanterTest,
  FlagSetTargetTest e2e, Phase5* suites exercising the full stack). Recommend
  enabling per-test reporting (Infection provides per-mutant attribution; a
  PHPUnit `--log-events`/coverage-per-test harness would formalize the check)
  once introduced.

## 12. Findings summary

| # | Area | Severity | Location(s) | Recommendation |
|---|------|----------|-------------|----------------|
| F1 | Test coverage | 🔴 High | 18 files (§11 table) | Close the 90%→95% gap; prioritize RoleClassifier, LineBasedRewriter, NamingContextExtractor, DictionaryNameResolver error paths. |
| F2 | Mutation testing | 🟠 Med | composer.json (absent) | Add `infection/infection` dev-dep + `composer mutation`; scope first runs to LineBasedRewriter + promotion targets. |
| F3 | Vocabulary hosting | 🟠 Med | `Detector:61`, `ParsedSourceCache:30`, `SemConvConfig:39-40`, `DisenchantScope:50` | Hoist public tunable constants onto their interfaces (`StringLiteralDetectorInterface`, `ParsedSourceCacheInterface`, `SemConvConfigInterface`, `DisenchantScopeInterface`). |
| F4 | Declaration order | 🟡 Low | 13 sites (§7.2 tables a–e) | Mechanical reorder pass; first decide whether `create()`-first is the official convention (then update AGENTS.md/ADR-0002 wording) or the prescribed order wins. |
| F5 | DI discipline | 🟠 Med | `MagicLiteralVisitor.php:47` | Remove `= new ContentClassifier()` parameter default; inject explicitly via `MagicStringVisitorFactory`. |
| F6 | Line length | 🟡 Low | §7.3 list | Reformat signatures one-parameter-per-line; wrap the extends/implements clause. |
| F7 | Const typing | ⚪ Info | `ParsedSourceCache.php:30` | `public const int DEFAULT_MAX_ENTRIES = 32;` for consistency. |
| F8 | Leakage measurement | 🟠 Med | Tooling | Introduce per-test/per-mutant coverage attribution alongside F2 to make the no-leakage rule verifiable. |

No architectural, security, SOLID, or correctness defects were found.

---

## 13. Conclusion

The post-consolidation library is architecturally excellent and stylistically
disciplined: enforced hexagonal boundaries, total interface backing, zero
abstract classes, zero `else`, zero Yoda, single-sourced patterns, and deliberate
modern-PHP choices. What separates it from this review's bar is concentrated and
actionable: **raise measured coverage from 90% to ≥95% (F1), adopt mutation
testing with per-test attribution (F2/F8), hoist public tunables onto ports
(F3), and run a mechanical declaration-order/line-length pass (F4/F6/F7)**.
Recommended sequence: F5+F3 (small, behavior-safe), F1 (largest effort, highest
value), F2+F8 (tooling that then guards F1's new tests), F4/F6/F7 last as a
style sweep.

---

## Appendix — verification commands run

```
composer architecture                                   # PASS (no violations)
vendor/bin/phpstan analyse src --level=9                # no errors
composer format:check                                   # 0 of 172 files to fix
vendor/bin/codecept run unit                            # OK (172 tests, 431 assertions)
vendor/bin/codecept run unit --coverage-xml             # 90.00% lines (1089/1210)
grep scans: else/elseif, Yoda, >120 cols, abstract,     # see §4, §7
  one-type-per-file, new-placement, const-hosting
php /tmp/kilo/order_check.php                           # declaration-order analyzer (findings manually verified)
php examples/05…, php examples/14…                      # smoke-runs OK (prior milestone evidence)
```

## Assumptions

- "Review-ready" mirrors the structure of the archived reviews
  (`docs/archive/REVIEW_*.md`): conceptual checklist up front, scorecard,
  per-dimension analysis, findings with `file:line`, verification commands.
- The declaration-order rule was interpreted as: *trait use → constants →
  properties/hooks → constructor → factory methods (named static constructors
  such as `create()`) → non-static methods → remaining static methods*, with
  public-before-protected/private within the non-static group. Interfaces count
  property-hook declarations in the properties section.
- Coverage is measured at unit-suite level with the available pcov driver;
  integration/acceptance suites are empty shells in this package, so unit
  coverage equals whole-library coverage.
- Generated Codeception support files (`tests/_support/_generated/*`) are exempt
  from the 120-column and style checks.
- Global PHP classes referenced with a leading backslash are treated as fully
  qualified (the file's own namespace never leaks), satisfying the imports rule.
