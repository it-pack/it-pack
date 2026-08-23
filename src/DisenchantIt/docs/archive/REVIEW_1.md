# DisenchantIt — In-Depth Engineering Review

> Scope: the `src/` library (≈3,100 LOC across 73 files), its public API, and the
> documentation/example surface. The review covers **Features, Architecture,
> SOLID / DRY, Hexagonal (ports & adapters), Modern PHP usage, Performance,
> Security, Maintainability and Developer Experience (DX)**.
>
> Method: source read of every production file, plus a run of the PHPArkitect
> architecture gate (`composer architecture` → **no violations**). No code was
> executed against a target project; claims about runtime behaviour are derived
> from the implementation and the runnable examples in `examples/`.

---

## 0. Scorecard (condensed)

| Dimension | Rating | One-line rationale |
|---|---|---|
| Features | **Strong** | Strings + numbers, typed consts, SemConv registry, idempotent, naming hints. |
| Architecture (hexagonal) | **Good / partly dormant** | Excellent port/interface discipline; layer enforcement is not yet mechanical. |
| SOLID | **Strong** | SRP & ISP well applied; one DIP seam worth tightening. |
| DRY | **Fair** | Naming logic triplicated; a couple of string/enum couplings. |
| Modern PHP | **Strong (selective)** | Readonly, enums, property hooks, WeakMap, `match` — used well; Lazy/Fibers unused (justified). |
| Performance | **Good** | One parse per op, reused parser; WeakMap + "streaming" under-deliver. |
| Security | **Strong** | Hard "never execute" rule upheld; no bounds on input size. |
| Maintainability | **Good** | Small focused classes, tests, examples; minor inconsistencies. |
| DX | **Strong** | Single entry point, runnable examples, clear docs. |

Overall: a **mature, well-shaped library** with a few high-value, low-risk refactors
(see §9). Nothing is broken; the findings are about leverage and consistency.

---

## 1. Features

DisenchantIt promotes *magic scalar values* (string **and** numeric literals) found in
PHP source into explicit `private const` declarations and, optionally, a shared
SemConv `interface` that every class aliases through.

What works today:

- **String magic values** — detected by AST role (`constructor_arg`, `comparison`,
  `branch_value`, `array_key`, …), filtered by identifier-shape and role, grouped
  by value, slugified to `UPPER_SNAKE_CASE` (`RoleClassifier`, `CandidateFilter`,
  `CandidateGrouper`, `ConstantNameGenerator`).
- **Numeric magic values** — `LNumber`/`DNumber` detected and, *only* when a naming
  hint is recoverable (unit suffix / preceding comment / variable-or-key name),
  promoted to **typed** constants (`private const int …` / `private const float …`)
  that preserve the raw token (`0x1F` stays `0x1F`). Trivial values (`0`, `1`,
  `0.0`, `1.0`) and hint-less numbers are excluded (`RoleClassifier.php:29`,
  `CandidateFilter.php:54-69`, `NamingContextExtractor.php`).
- **Placement** — `SameClass` / `PerClass` / `Auto` strategies decide *where* the
  constant lives (`ConstantPlacementStrategyInterface`, `AutoConstantPlacementStrategy`).
- **Line-based rewrite** — no AST reprinting; untouched lines stay byte-identical,
  insertions applied bottom-up to preserve line offsets (`LineBasedRewriter`).
- **SemConv collect + alias** — merge placements into one `interface`, then alias
  each class const's value to `SemConv::NAME`; method bodies untouched
  (`SemConvCollector`, `SemConvAliasRewriter`).
- **Idempotency & safety** — a constant's own declaration value is never re-promoted
  (`MagicLiteralVisitor::isInsideClassConstDeclaration`); duplicate declarations and
  already-aliased lines are skipped; differing-value collisions are a **hard error**
  (`SemConvCollector.php:51-73`).

This is a genuinely useful, coherent feature set. The numeric support is the most
recent and is the weakest-documented *in code*, though the user-guide and the new
`examples/12_*` and `examples/13_*` demos cover it well.

---

## 2. Architecture — Hexagonal / Ports & Adapters

### 2.1 What is excellent

- **Every concrete class implements an interface.** 34 `final` classes, all backed by
  a port (`DisenchanterInterface`, `Detector`→`StringLiteralDetectorInterface`,
  `LineBasedRewriter`→`RewriterInterface`, the strategies, factories, every value
  object, …). Enforced by the `finalClassesMustBeBackedRule` in `phparkitect.php:120`
  and **verified green**. This is the single most important property for
  testability and swap-ability, and it is nailed.
- **One-directional dependencies at the port level.** `Contract` depends only on
  interfaces (enforced by `interfaceDependencyRule`, `phparkitect.php:48`), so the
  port layer is free of internal and vendor coupling.
- **Composition via factories.** The single public entry point
  `DisenchanterFactory::create()->build()` wires the whole graph; collaborators are
  injected, not `new`-ed inside domain logic (`DisenchanterFactory`, `DetectorFactory`).

### 2.2 What is aspirational (and should be stated honestly)

The PHPArkitect *layered* rules (`phparkitect.php:31-41`) define `Contract` / `Core` /
`Adapter` / `ValueObject` / `Factory` components, but the file comment at
`phparkitect.php:24-30` admits they "activate as the refactor introduces the
directories." **Today all production code is flat under `ItPack\DisenchantIt\`** —
there is no `Core` or `Adapter` namespace. Arkitect only assigns components by
namespace, so the `where(...)->mayDependOnComponents(...)` constraints simply do not
apply to the root-namespace classes. In other words:

- The **generic** rules (final, backed-by-interface, interfaces-only-depend-on-interfaces,
  factory-final) are *enforced* and passing.
- The **layered** rules (Adapter may depend only on Contract/ValueObject, Core never
  on RunIt, etc.) are **dormant** until the directory split lands.

This is fine and honest, but the "hexagonal architecture is enforced" claim in
`AGENTS.md` should be read as *"enforced in principle / by-convention, with the
mechanical gate pending the namespace split."* The code *is* already adapter-shaped
(Detector = adapter over nikic, LineBasedRewriter = adapter over strings), so the
split is mostly mechanical packaging.

### 2.3 Minor layering observation

`NamingContextExtractor` (root namespace) depends on the concrete `NamingContext`
value object. That is legitimate once it lives under `Adapter` (Adapters may depend
on `ValueObject`), but today it is a concrete→concrete dependency in the flat tree.
No rule flags it (dormant layers), but it is the kind of coupling the future
`Adapter` namespace will formalise.

---

## 3. SOLID

- **SRP** — Strong. `Detector` only scans; `CandidateFilter` only filters;
  `CandidateGrouper` only groups; `LineBasedRewriter` only rewrites; `SemConvCollector`
  only renders the registry; `SemConvAliasRewriter` only aliases. Each file has one
  reason to change.
- **OCP** — Good. New roles, filters, grouping strategies, or placement strategies
  are added by *implementing a port*, not by editing existing classes.
- **LSP** — Good. Every strategy/collector is substitutable via its interface.
- **ISP** — Excellent. Interfaces are small and role-specific (e.g.
  `RoleClassifierInterface`, `NamingContextExtractorInterface`,
  `ParsedSourceCacheInterface`). No fat interfaces.
- **DIP** — Strong, with one seam to tighten: `DisenchanterFactory`
  (`src/Factory/DisenchanterFactory.php:32-40`) depends on **concrete** collaborators
  (`DetectorFactory`, `ConstantPlacementStrategyFactory`, `LineBasedRewriter`,
  `SemConvCollector`, `SemConvAliasRewriter`). For a *composition root* this is
  acceptable, but it does mean the orchestration cannot be reconfigured without a new
  factory subclass. The `DisenchanterFactoryInterface` port exists, so the seam is
  there; it is simply not the default consumers reach for. Low priority.

---

## 4. DRY

Three concrete duplications are worth fixing:

1. **`nameFor()` is implemented three times** with identical branching —
   `src/Disenchanter.php:72-79`, `src/SameClassConstantStrategy.php:56-65`,
   `src/PerClassConstantStrategy.php:56-65`. Each does
   *"if string → `generator`, else → `numericGenerator`."* The `Candidate` value
   object **already carries `constantName`** (`src/ValueObject/Candidate.php:13`), yet
   the placement strategies ignore it and recompute the name from `occurrences[0]`.
   → Risk: if naming logic changes, three sites must be kept in sync; a divergence
   would silently rename a constant between detection and placement.
   → Fix: compute the name once (in the grouper/detector) and let placement read
   `Candidate::constantName`; or extract a single `NameResolver` service.

2. **`ConstantPlacement->type` is a `string` that *coincidentally* equals the PHP
   type keyword** (`int`/`float`/`string`). `LineBasedRewriter` emits it directly as
   `private const %s …` and `SemConvCollector` rehydrates it via
   `LiteralKind::from($placement->type)` (`src/SemConvCollector.php:87`). The coupling
   is by *value*, not by type. If `LiteralKind` cases were ever renamed, both break
   with no compile-time signal. The port comment at
   `ConstantPlacementInterface.php:19-24` explains this was a deliberate choice to keep
   the port free of the enum (PHPArkitect one-directional rule) — fair — but the two
   consumers should share one canonicalisation helper rather than each trusting the
   raw string.

3. **`CandidateGrouper` inlines `new` of its collaborators** as constructor defaults
   (`src/CandidateGrouper.php:20-21`). This violates the project's own
   "no inline `new` of collaborators; use factories" rule. The grouping strategies are
   stateless, so this is low-risk, but it is a deviation from the stated discipline
   and is inconsistent with how every other collaborator is injected.

The `LiteralValueObjectTrait` (`src/ValueObject/LiteralValueObjectTrait.php`) is a
*good* use of a trait for the shared `needle()` behaviour — though it hosts a single
3-line method, so a tiny shared base or just duplicating it would also be defensible.
Net: DRY is mostly good; the `nameFor` triplication is the one that genuinely matters.

---

## 5. State-of-the-art PHP feature usage

| Feature | Used? | Assessment |
|---|---|---|
| `readonly` classes/props | **Yes** (72 sites) | Pervasive and appropriate; see §6 caveat. |
| Backed `enum` | **Yes** (`LiteralKind`) | Correct choice for a closed internal state set (per `AGENTS.md`). |
| Property hooks (`{ get; }`) | **Yes** (interfaces) | Idiomatic: ports declare `{ get; }`; implementations satisfy them with plain `public` props. Exactly the documented pattern (`LiteralInterface.php:11-14`). |
| `WeakMap` | **Yes** (`ParsedSourceCache`) | Correct tool for a non-owning cache — but see §7 (under-used). |
| `match` | **Yes** | Used cleanly in `CandidateFilter`, `SemConvCollector`, `RoleClassifier`. |
| Constructor promotion | **Yes** | Used throughout; keeps VOs terse. |
| Traits | **Yes** (one) | `LiteralValueObjectTrait` — acceptable. |
| Lazy objects / `GhostObject` | **No** | Parser uses first-use lazy init (`Detector.php:73-75`). Reasonable given the `Lazy` API churn across PHP 8.4→8.5; a `GhostObject`/`newLazyGhost` could replace it, but manual init is clearer and vendor-free. |
| Fibers / async | **No** | Not needed for a single-source-string API; AGENTS mentions them only for future multi-file overlap. The current "streaming" method does not use them (see §7). |
| `WeakReference` | **No** | Not required. |
| Virtual / computed properties | **No** | Could be used (e.g. `SemConvConfig::fullyQualifiedName()` as a virtual property), but a method is perfectly clear. No gap. |

**Verdict:** modern-PHP adoption is *judicious*, not decorative. Features are used
where they earn their place (readonly for immutability, enum for closed state,
property hooks at port boundaries, WeakMap for the non-owning cache). The absence of
Lazy/Fibers is justified by the API shape, not by omission.

---

## 6. Performance

Strengths:

- **One parse+traverse per operation.** `Detector::scanSource` parses once; the nikic
  parser is built **lazily and reused** for the lifetime of the `Detector`
  (`Detector.php:51,73-75`). So a single built `Disenchanter` scanning *N* distinct
  files costs *N* parses and *1* parser construction.
- **Line-based rewrite is O(lines × placements)** with simple string ops — no AST
  reprint, no full-file regeneration. Fast and formatting-preserving.
- **`WeakMap` cache** (`ParsedSourceCache`) is conceptually right for bounding memory
  across many distinct sources.

Findings (performance-relevant correctness of the optimisation story):

1. **The WeakMap cache is effectively dead in the normal flow, and the
   "parse once across detect → rewrite → SemConv" doc claim is inaccurate.**
   `Disenchanter::detect()` builds a **brand-new `SourceCode`** on every call
   (`src/Disenchanter.php:54`), and both `rewrite()` (`:81`) and
   `rewriteWithSemConv()` (`:101`) call `detect()` — so each public method parses
   once with a *different* `SourceCode` instance. `ParsedSourceCache` keys by
   **object identity** (`src/ParsedSourceCache.php:23`), not by content, so the cache
   never hits across those calls. The `SourceCode::identity()` content hash
   (`src/ValueObject/SourceCode.php:19-22`) exists but is **not used for caching**.
   → Consequence: calling `detect()` then `rewrite()` on the same source parses it
   twice. → Fix (either): (a) cache by `SourceCode::identity()` so identical sources
   share a parse; or (b) build one `SourceCode` in the orchestrator and thread it
   through `detect`/`rewrite`/`rewriteWithSemConv` so the *same* instance hits the
   WeakMap. Option (b) is the truest realisation of the stated P4 intent.

2. **`scanSourceStreaming` is nominal streaming.** It does a full
   `scanSource` (entire AST in memory) and `yield from`s the resulting array
   (`Detector.php:104-107`). There is no incremental / file-chunk streaming, so for a
   very large single file the whole AST is still materialised. Either implement real
   streaming or rename/document the method as a convenience generator to avoid
   over-promising.

3. **No input-size bound.** A huge file → a huge AST and a large literal list held in
   the visitor. The non-owning WeakMap bounds *cached* entries, but the *active* scan
   is unbounded. `AGENTS.md` security guidance calls for bounded payloads; a
   configurable max-source-size guard (or a parse-error/timeout budget) would close
   that gap and also harden against pathological inputs.

None of these are performance regressions for typical inputs; they are about
*delivering on stated optimisations* and *robustness at scale*.

---

## 7. Security

Strengths:

- **The hard rule is real and pervasive:** nothing is ever `require`d, `include`d,
  `class_exists()`-ed, or executed. Detection is pure AST-in/data-out; rewriting is
  pure text-in/text-out (`Detector.php:19-30`, `Disenchanter.php:31-33`). This
  eliminates the entire class of "refactor tool executes your code" vulnerabilities.
- **Fail-closed on conflict.** A SemConv name with two differing values throws
  (`SemConvCollector.php:62-73`) rather than silently overwriting — correct.
- **`existingSemConvSource` is passed in, never read from disk** by the collector, so
  merge semantics are explicit and side-effect-free (`SemConvCollector.php:41`).

Findings:

1. **Unbounded input size** (see §6.3) — a memory-exhaustion / DoS vector for
   untrusted or accidentally-huge sources, and a gap against the project's own
   "bounded payloads" security requirement.
2. **Regex/string-based rewriting edge cases** are correctness, not security, but
   worth noting in the same breath: `LineBasedRewriter` replaces the **first**
   occurrence of the needle on the recorded line (`src/LineBasedRewriter.php:58-61`).
   Because the line is taken from the AST node's start line, the needle is always the
   literal in its real context, so a commented-out twin on the same line is not a
   risk. The known limitation is "one replacement per line" — two identical literals
   on one line see only the first replaced. Not a security flaw; a documented
   limitation.
3. **No secret handling concerns** — the tool only emits source it was given; it does
   not log or transmit analysed contents. Good.

---

## 8. Maintainability & DX

Strengths:

- **Small, focused, `final readonly` value objects and services**; easy to reason about.
- **Extensive docblocks** explain *why* (idempotency bugs found in practice, the
  nette/php-generator failure mode, the SemConv merge rationale). High transfer-of-
  context value.
- **Runnable examples** (`examples/01_*` … `14_*`, plus the new `12_magic_numbers.php`
  and `13_magic_numbers_semconv.php`) that execute against the library — excellent for
  onboarding and as living documentation.
- **Clean public API:** `DisenchanterFactory::create()->build()` → `detect()` /
  `rewrite()` / `rewriteWithSemConv()`. One seam, sensible defaults.
- **Tests + PHPStan(level 9) + PHPArkitect + PHP-CS-Fixer** wired as composer scripts.

Caveats:

- **Inconsistent `readonly`.** Some services are `final readonly class`
  (`CandidateFilter`, `NumericNameGenerator`, `SemConvCollector`,
  `SemConvAliasRewriter`, `ParsedSourceCache`, factories, VOs), while others are
  merely `final class` despite being stateless
  (`LineBasedRewriter`, `RoleClassifier`, `SameClassConstantStrategy`,
  `PerClassConstantStrategy`). `Detector` and `MagicLiteralVisitor` are correctly
  *not* readonly (they hold lazy/mutable state). The stateless ones *should* be
  `readonly` for consistency and to make immutability a compiler-enforced guarantee.
- **`detect()` return key semantics** (`src/Disenchanter.php:50,62`): documented as
  "keyed by literal value," but the key is actually the *grouping key*
  (`i:5000|…signature` for numbers). For strings it is the value; for numbers it is
  not. A consumer iterating the returned array and relying on the key as "the literal
  value" will be surprised. Either key by a stable value or document the key as the
  group key.
- **Doc/behaviour drift on caching & streaming** (§6.1, §6.2) — the inline docblocks
  promise more than the current flow delivers. Updating those docblocks after the fix
  (or as part of it) keeps the code honest.

---

## 9. Prioritised recommendations

| # | Priority | Finding | Location | Suggested action |
|---|---|---|---|---|
| 1 | **High (easy win)** | `SourceCode` re-created per `detect()`, so the WeakMap never hits across `detect`/`rewrite`/`rewriteWithSemConv`; double-parses identical source. | `Disenchanter.php:54`, `ParsedSourceCache.php:23` | Thread one `SourceCode` instance through the pipeline, **or** cache by `SourceCode::identity()` (content hash) instead of object identity. Delivers the promised P4 "parse once." |
| 2 | **Medium** | `nameFor()` triplicated; placement ignores `Candidate::constantName`. | `Disenchanter.php:72-79`, `SameClassConstantStrategy.php:56-65`, `PerClassConstantStrategy.php:56-65` | Single `NameResolver` (or read `Candidate::constantName`); remove the recomputation. |
| 3 | **Medium** | `ConstantPlacement->type` coupled by *string value* to `LiteralKind`. | `ConstantPlacement.php:17`, `SemConvCollector.php:87`, `ConstantPlacementInterface.php:19-24` | Centralise canonicalisation (`LiteralKind::from` + emit) in one helper used by both consumers. |
| 4 | **Low** | `CandidateGrouper` inlines `new` of collaborators. | `CandidateGrouper.php:20-21` | Inject via factory to match the project's own DI discipline. |
| 5 | **Low** | Stateless services not `readonly`. | `LineBasedRewriter`, `RoleClassifier`, `SameClassConstantStrategy`, `PerClassConstantStrategy` | Declare `final readonly class`. |
| 6 | **Low** | `detect()` key documented as "literal value" but is the group key. | `Disenchanter.php:50,62` | Fix docblock or key stabilisation. |
| 7 | **Low** | `scanSourceStreaming` is not real streaming. | `Detector.php:104-107` | Implement chunked streaming or document as a convenience generator. |
| 8 | **Low (robustness)** | No input-size bound. | `Detector.php:81` | Optional max-source-size / parse-timeout guard (also a security hardening). |
| 9 | **Process** | Layered PHPArkitect rules are dormant until `Core`/`Adapter` namespaces exist. | `phparkitect.php:24-41` | Land the directory split to activate true layer enforcement; until then, state the gate as "port-discipline enforced, layer enforcement pending." |

None of these block adoption. #1 and #2 are the highest-leverage: #1 reclaims a real
optimisation that is currently only documentary, and #2 removes a genuine
correctness-risk duplication.

---

## 10. Verdict

DisenchantIt is a **well-engineered, principled library**. Its port/adapter split is
real (every concrete is backed by an interface, the architecture gate is green), its
use of modern PHP is measured and appropriate, and its core safety invariant — *never
execute the code you analyse* — is honoured throughout. The feature set (strings **and**
typed numbers, placement, SemConv, idempotency) is coherent and genuinely useful.

The review's substance is in the *leverage* opportunities, not defects: (1) make the
parse-once/WeakMap optimisation actually fire by keying on content/instance identity,
(2) de-duplicate the naming logic, and (3) tighten the few string/enum couplings and
the `readonly` consistency. Addressing #1–#3 would move the code from "clean and
correct" to "clean, correct, and fully delivering on its stated performance and
layering promises" without changing a single line of public API.
