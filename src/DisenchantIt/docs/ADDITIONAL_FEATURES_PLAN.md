# DisenchantIt — Additional Features Plan (brainstorm & reasoning)

> Status: **brainstorm / proposal**. Nothing here is committed to a roadmap yet.
> It documents *what else DisenchantIt could offer*, with reasoning about whether
> each idea fits the domain, the architecture (ADR-0001…0005, ADR-0011), and the
> hard rule (source-text in, source-text out; never execute the analysed file).
>
> The brief emphasised *"additional scalar value types beyond strings and
> numbers, where it makes sense"*. The short answer (§1) is that PHP has almost
> no *other* scalar literal kinds worth promoting standalone — so the real
> leverage is elsewhere: extend **string coverage**, add a **semantic sub-type
> taxonomy** of the strings/numbers we already handle, and widen the **promotion
> target** (enums, flag sets). The details and the full feature list follow.

---

## 1. The honest answer on "other scalar types"

PHP's literal grammar, at the point `nikic/php-parser` models it, yields only
three scalar literal node kinds that carry a user-authored value:

| Kind | Parser node | Currently detected? |
|------|-------------|---------------------|
| string | `Scalar\String_` | ✅ (`MagicLiteralVisitor.php:64`) |
| int | `Scalar\LNumber` | ✅ with naming hint (`MagicLiteralVisitor.php:85`) |
| float | `Scalar\DNumber` | ✅ with naming hint |

Everything else that *looks* scalar in source is **not** a "magic scalar value"
worth extracting:

- **`true` / `false`** → `Expr\ConstFetch` (name `true`/`false`), not a `Scalar`.
  They are already self-describing keywords. Promoting a bare `true` into
  `const bool ENABLED = true;` is noise, and promoting a `true` that is one
  operand of `===` is meaningless. The *meaningful* unit there is the
  **flag/call site**, not the boolean token.
- **`null`** → `Expr\ConstFetch` (`null`). Same argument: self-describing;
  promoting default-parameter `= null` would create `const ?T DEFAULT = null`.
- **Magic constants** (`__FILE__`, `__DIR__`, `__LINE__`, `__METHOD__`, …) →
  engine-resolved, never user magic values. Must stay excluded.
- **`::class`** → already produces a canonical class-name string; it is a
  *reference*, not a magic value, and must never be promoted.

**Conclusion:** the only genuinely "new scalar kinds" are booleans and `null`,
and both are **poor candidates** for standalone promotion. So the productive
reading of the brief is:

1. **Extend string coverage** to the string syntaxes we *don't* yet scan
   (heredoc/nowdoc, encapsed fragments, FQCN-shaped strings) — still `String`
   kind, just more of it.
2. **Sub-type the strings and numbers we already have** by *content/shape*
   (URL, regex, SQL, path, version, bitmask, status code, …) to drive better
   **names** and **targets**. This is where the value is.
3. **Widen the promotion target** beyond `private const`: backed enums and
   bitmask/flag sets, plus shared/global constants.

The rest of this document expands those three directions plus the operational
features that make them safe and useful.

---

## 2. Current capability snapshot (so proposals stay grounded)

Detection / policy pipeline (all under `src/`, ports in `Contract\`, impls in
`Adapter\`):

- **Visitor** (`Adapter\MagicLiteralVisitor`) collects `String_`, `LNumber`,
  `DNumber`, tagging each with a `LiteralRole` and the **exact raw token**
  (`0x1F` stays `0x1F`), and excludes a constant's own declaration value so
  rewrites are idempotent.
- **Role** (`ValueObject\LiteralRole`, backed enum) infers *where* a literal
  sits: `ConstructorArg`, `MethodCallArg`, `ArrayKeyLiteral`, `Comparison`,
  `BranchValue`, `EnumBackingValue`, `ClassConstValue`, `Unclassified`, ….
- **Filter** (`Adapter\CandidateFilter`) keeps identifier-shaped strings
  (`RoleClassifier::looksIdentifierLike`), drops exception messages / format
  strings / enum backing values, and promotes numbers **only** when a naming
  hint exists and the value is non-trivial (`0`, `1`, `0.0`, `1.0` excluded).
- **Kind** (`ValueObject\LiteralKind`, backed enum): `String|Int|Float` only.
- **Placement** (`CompositeConstantPlacementStrategy` + Same/PerClass
  strategies) decides *where* the `private const` lives.
- **Rewriter** (`Adapter\LineBasedRewriter`) inserts `private const` and
  replaces occurrences with `self::CONST`, line-scoped.
- **SemConv** (`Adapter\SemConvCollector` / `SemConvAliasRewriter`) builds a
  shared, cross-file registry of canonical name→value pairs.
- **Execution** (`Contract\ExecutionRecorderInterface` + `Adapter\NullExecutionRecorder`,
  `ValueObject\Execution\*`) records versioned occurrences (`disenchant.it.*.v1`).

> Doc-drift note: `README.md` still mentions `AutoConstantPlacementStrategy`;
> the code renamed it to `CompositeConstantPlacementStrategy`. Unrelated to this
> plan, but worth a one-line README fix.

---

## 3. Guardrails that bound every proposal

From `AGENTS.md` and the ADRs:

- **Never execute/autoload** the analysed file. Detection is AST-in, data-out;
  rewriting is text-in, text-out. Any new detector must stay at the AST level.
- All new concrete classes implement a port (ADR-0004: final + backed), live in
  the right namespace (ADR-0001), are built by a factory (ADR-0002), and are
  `readonly`/immutable where possible. Closed internal sets stay backed enums
  (`LiteralKind`, `LiteralRole` are the pattern).
- **Core never imports `ItPack\RunIt\*`** (ADR-0005). New behavior is a port +
  adapter, not a Core change.
- Don't invent a shared abstraction for *sharing across classes* unless asked —
  `PerClass` vs shared `SemConv` is a human call (the SemConv path is the
  explicit shared one).
- Idempotency: a constant's own declaration value is never re-promoted.

---

## 4. Direction A — extend STRING coverage (still `String` kind)

These add detectable surface without inventing a new `LiteralKind`; they reuse
the existing `String` kind, role pipeline, and filter.

### A1. Heredoc / nowdoc bodies (`Scalar\Heredoc`, `Scalar\Encapsed`)
- **What:** SQL, DSN, GraphQL, big template strings written as `<<<SQL … SQL`.
- **Makes sense?** Yes — a repeated SQL/DSN string is a classic magic value.
- **Risk:** bodies can be large prose → must be gated by a **size cap** and the
  existing identifier-shape filter does *not* apply (SQL has spaces). So a
  *content* check (looks like SQL/DSN, repeated across sites) is needed, not the
  generic `looksIdentifierLike`.
- **Shape:** new visitor branch in `MagicLiteralVisitor`; kind stays `String`;
  a `ContentClassifier` (new port) tags it `LiteralSubkind::Sql` etc. for naming
  (`SQL_FIND_USER`). Default **off** for multi-line bodies; opt-in.

### A2. Encapsed string fragments (`Scalar\Encapsed` / `EncapsedStringPart`)
- **What:** `"Hello $name"` → the literal part `Hello `.
- **Makes sense?** Marginal. Fragments are usually prose; promoting a fragment
  of an interpolation is rarely desirable and risks breaking the interpolation.
- **Recommendation:** **skip** by default; only consider when the fragment is
  identifier-shaped and repeated as a standalone key/label.

### A3. Fully-qualified class-name strings (`'App\\Foo\\Bar'`)
- **What:** a string whose value is a class/interface name.
- **Makes sense?** Ambiguous. These are *references*, not magic values. Two
  sub-options:
  - (a) **Leave them alone** (default) — they're already canonical-ish and
    often need to stay dynamic.
  - (b) **Micro-refactor to `::class`**: `'App\\Foo\\Bar'` → `Bar::class`.
    High value but **risky**: only safe when the string is *not* dynamically
    concatenated and the class is loadable. Best as a *separate, explicit*
    opt-in adapter, never bundled with generic magic-value promotion.
- **Recommendation:** detect-and-**exclude** by default (so they don't get
  promoted to a string const), with an optional `::class` converter as its own
  feature (low priority, needs care).

---

## 5. Direction B — semantic SUB-TYPING of strings & numbers we already handle

This is the highest-value direction. `LiteralRole` tells us *where* a literal
sits; a new `LiteralSubkind` (backed enum) would tell us *what it is* by
**content**, driving better constant **names** and the right **target**.

### B1. New `LiteralSubkind` enum + `ContentClassifier` adapter
- Cases (all closed internal state → backed enum, per ADR-0003):
  `Url`, `FilePath`, `Regex`, `Sql`, `Json`, `DateTimeFormat`, `Locale`,
  `Charset`, `MimeType`, `Version`, `HashAlgorithm`, `HttpMethod`,
  `HttpStatus`, `EventTag`, `ConfigKey`, `Bitmask`, `StatusCode`, `TimeUnit`,
  `Size`, `Port`, `Unknown`.
- A `ContentClassifierInterface` port (new) + `Adapter\ContentClassifier`
  inspects the raw token + role + naming context with cheap shape heuristics
  (regex for URL/regex/version, context for SQL/status, power-of-two for
  bitmask…). No execution, pure string/AST inspection.
- **Why it fits:** it's an additive adapter behind a port; Core doesn't change;
  the rewriter/naming reuse it to emit `URL_API = '...'`, `REGEX_EMAIL = '...'`,
  `SQL_FIND_USER = '...'` instead of a generic `CONST_1`. Naming becomes
  self-documenting.

### B2. Bitmask / flag-set detection (numeric sub-kind `Bitmask`)
- **What:** integer literals that are powers of two (`1`, `2`, `4`, `8`, `16`…)
  appearing in `|`/`&` expressions, or a set of such used together.
- **Makes sense?** Strongly — this is the canonical "secretly an enum" case for
  ints, currently invisible because bare numbers lack a naming hint and are
  filtered out.
- **Better target:** not a flat `const int`, but a **flags constant set**:
  `const int READ = 1; const int WRITE = 2; const int ALL = self::READ | self::WRITE;`
  — or a backed enum with `BackedEnum::cases()` + bitwise combos. This pairs
  with Direction C2.
- **Detection hook:** `ContentClassifier` marks `LiteralSubkind::Bitmask` when
  `($n & ($n - 1)) === 0` and the literal participates in a bitwise op, or when
  several powers-of-two share a variable. Naming: `PERMISSION_READ`, etc.

### B3. Numeric "meaning" sub-kinds (status/error/exit codes, ports, time units, sizes)
- Recognise by **context + content**: an int compared with `===` against a
  small known set in one variable → `LiteralSubkind::StatusCode`; an int with a
  `// ms`/`// s` comment → `TimeUnit`; an int like `3306`/`6379` near a DSN →
  `Port`; `0`, `1` trivial stay excluded.
- Drives **typed** constants (`private const int HTTP_NOT_FOUND = 404;`) and
  documents intent. Reuses the existing typed-constant emission.

### B4. Closed-set detection → enum candidacy
- When several *distinct* string (or int) literals appear in the **same
  variable/parameter/array-key context** (e.g., a `$status` always set to one
  of `'new'|'active'|'closed'`), that set is *closed* and a strong enum
  candidate (feeds Direction C1). This is a grouping/analysis pass over the
  already-detected candidates, not new scanning.

---

## 6. Direction C — widen the PROMOTION TARGET

Currently the only target is a class `private const`. The README itself says
*"and, eventually, backed enums"*.

### C1. Backed enum as a target (high value)
- When a literal set is **closed** (B4) and same-typed, generate
  `enum Status: string { case New = 'new'; case Active = 'active'; … }` and
  rewrite usages to `Status::New`.
- A new `PlacementTarget` port with strategies: `ClassConstantTarget`
  (current), `BackedEnumTarget`, `SharedConstantTarget` (global/namespace
  level). The existing `ConstantPlacementStrategyInterface::supports()` seam
  (ADR-0004-friendly, OCP) extends naturally to *target* strategies.
- **Guard:** never promote a value that is already an enum backing value
  (`LiteralRole::EnumBackingValue` is already excluded by the filter) — we'd
  otherwise corrupt an existing enum.

### C2. Flag-set / bitmask target (pairs with B2)
- Emit a flags constant group (or a flags-backed enum) instead of scattered
  `const int`s, with the combined `ALL` member. Higher cohesion for bitmask
  magic numbers.

### C3. Shared / global constant target
- For tokens used app-wide (config keys, event tags) the existing **SemConv**
  path already builds a shared registry. Expose a *target* choice: per-class
  `private const` (default) vs the shared SemConv registry vs a namespace-level
  `const`. Keeping it a strategy honours "sharing is a human call".

---

## 7. Direction D — operational / workflow features

These make the richer detection safe and actionable.

### D1. Scope & ignore controls
- Whitelist/blacklist by path glob, namespace, class, or literal value; ignore
  `tests/`, `fixtures/`, vendor. Essential once noisier kinds (heredoc, flags)
  are enabled. A `ScopeFilter` port + config VO (`DisenchantConfig` extension).

### D2. Output & reporting formats
- `--dry-run` (no write), unified **diff**, **JSON** report (machine-readable
  for an IDE quick-fix or bot), and **SARIF** for CI annotation. The just-added
  `ExecutionRecorderInterface` (ADR-0011 occurrences) is the natural feed for
  these — a `JsonReportRecorder` / `SarifRecorder` adapter implements the same
  port, no Core change.

### D3. More syntactic positions
- Today detection covers method bodies. Extend the visitor to also collect
  literals in **attribute arguments**, **property defaults**, **constant
  initializer expressions**, and **`match`/`enum` arms** (some already via
  roles). Pure AST extension; same pipeline.

### D4. Naming dictionary / policy
- Optional alias file mapping a literal → desired name, plus casing/prefix
  policy. Improves generated names for the sub-kinds in B1. A `NamePolicy` VO +
  optional `DictionaryNameResolver` step.

### D5. SemConv persistence (cross-run canonical dictionary)
- Persist the generated SemConv name→value map so re-runs (and sibling repos)
  keep **stable** canonical names. The registry already exists; this adds
  load/merge persistence behind a port (file/store adapter).

### D6. Post-rewrite verification hook (out-of-process, safe)
- `php -l` / running the target's own test suite is explicitly a *separate
  process* concern (README hard rule). Offer it as an **optional observer**
  behind the execution port (a `VerificationObserver` adapter), never inside
  Core.

---

## 8. Prioritized proposal

Severity = user value vs risk; Effort = S/M/L. "Fits arch" = additive port +
adapter, Core unchanged.

| # | Feature | Direction | Severity | Effort | Arch fit | Notes |
|---|---------|-----------|----------|--------|----------|-------|
| 1 | `LiteralSubkind` enum + `ContentClassifier` | B1 | 🔴 | M | ✅ port+adapter | best ROI: better names, feeds C1/C2 |
| 2 | Backed-enum target | C1 | 🔴 | L | ✅ strategy | README's "eventually enums"; biggest feature |
| 3 | Bitmask/flag-set detection + target | B2/C2 | 🟠 | M | ✅ | high-value int magic values |
| 4 | Heredoc/nowdoc string coverage | A1 | 🟠 | S | ✅ | opt-in, size-gated |
| 5 | Scope & ignore controls | D1 | 🟠 | M | ✅ | prerequisite for noisier kinds |
| 6 | JSON / SARIF / diff reporting | D2 | 🟡 | M | ✅ | feeds off ExecutionRecorder |
| 7 | Numeric meaning sub-kinds (status/port/time) | B3 | 🟡 | M | ✅ | naming + typed const |
| 8 | Closed-set → enum candidacy pass | B4 | 🟡 | M | ✅ | analysis over existing candidates |
| 9 | Shared/global constant target | C3 | 🟡 | S | ✅ | builds on SemConv |
| 10 | SemConv persistence | D5 | 🟡 | M | ✅ | cross-run stability |
| 11 | More syntactic positions | D3 | 🟡 | S | ✅ | visitor extension |
| 12 | Naming dictionary / policy | D4 | 🟢 | S | ✅ | polish |
| 13 | FQCN-string → `::class` converter | A3b | 🟢 | M | ✅ | explicit opt-in, risky |
| 14 | Post-rewrite verify observer | D6 | 🟢 | S | ✅ | out-of-process |
| — | **Promote bare `true`/`false`/`null`** | — | — | — | ❌ | **rejected**: noise, self-describing |
| — | **Encapsed fragments** | A2 | — | — | ❌ | **rejected**: breaks interpolation |
| — | **Magic constants / `::class` as values** | — | — | — | ❌ | **rejected**: references, not magic |

---

## 9. Open questions (need a human call before building)

1. **Booleans:** agreed to *not* promote bare `true`/`false` standalone. But
   should we offer a *targeted* "promote the boolean flag at a config-setter
   call site" micro-feature? (Leaning no — too niche, noisy.)
2. **Enum vs const default:** when a closed string set is found, is the default
   target a backed enum or a shared SemConv constant? (Affects C1 vs C3 default.)
3. **Aggressiveness on FQCN → `::class`:** opt-in only, and only for
   non-concatenated, loadable classes — acceptable, or out of scope entirely?
4. **Heredoc default:** keep multi-line bodies **off** by default (recommended)
   or on with a size cap?
5. **Subkind naming style:** `SQL_FIND_USER` (subkind-prefixed) vs
   `FIND_USER_SQL` (suffixed) vs plain `FIND_USER` — pick a convention for
   `ConstantNameGenerator` to consume.

---

## 10. Non-goals (explicit)

- Promoting `true`/`false`/`null` as standalone constants.
- Rewriting references that are *not* magic values (FQCN strings, `::class`,
  magic constants).
- Executing or autoloading analysed files, or re-evaluating rewritten source
  inside Core (verification stays a separate process / optional observer).
- Inventing a shared cross-class abstraction unless the user opts into the
  SemConv/shared path.
- Any `ItPack\RunIt\*` dependency in Core (ADR-0005); RunIt remains a future
  bridge adapter.

---

## 11. Suggested first slice

If we build one coherent vertical first: **#1 (`LiteralSubkind` +
`ContentClassifier`) → #5 (scope/ignore) → #2 (backed-enum target) → #3
(bitmask)**. That delivers "recognise *what* the magic value is, name it well,
and promote closed sets to enums/flags" — the highest-value, architecturally
clean path — while leaving booleans/null/references correctly alone.
