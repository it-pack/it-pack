# Plan: Disenchanting Magic Numbers

> Status: **Brainstorming / design proposal — not yet implemented.** Candidate ADR
> (record in `docs/REFACTORING_PLAN.md` once the shape is agreed). Built on the
> current state of the codebase (`src/`), the hexagonal layout, the
> source-text-safety hard rule, and the existing detect → filter → group → name
> → place → rewrite (+ SemConv) pipeline.

---

## 0. TL;DR

Magic numbers are *not* "strings with a different type". The hard part is not
detection (nikic already gives us `LNumber`/`DNumber` nodes) — it is that
**a number has no inherent name**, and **its source representation must be
preserved exactly** for a line-based rewrite. So the work is:

1. **Generalise the literal model** from `StringLiteral` to a `LiteralInterface`
   supertype covering strings *and* numbers, carrying both a parsed `value` and
   the `raw` source token (separators, hex/bin prefix, sign context).
2. **Make naming context-aware**: promote a number only when a meaningful name
   can be derived (variable/parameter name, preceding comment, unit suffix,
   config key). No hint ⇒ no promotion (same discipline as "identifier-shaped
   only" for strings).
3. **Make the pipeline kind-aware** without forking it: typed `const int` /
   `const float` / `const string`, type-aware SemConv registry, raw-text
   replacement, and a numeric-aware candidate filter + grouping.
4. **Refactor the core to lean on PHP 8.4 features where they earn their place**:
   `WeakMap` AST/result cache keyed by a `SourceCode` VO, **lazy objects** for
   the parser stack, **property hooks** for computed VO properties, a focused
   **trait** for shared literal fields, a `LiteralKind` **enum**, and
   **generators** for streaming large scans. **Fibers** belong only in a
   consumer-side runtime adapter (overlap I/O with parsing), never in Core.

---

## 1. Why numbers are different from strings

| Concern | Magic strings | Magic numbers |
|---|---|---|
| Inherent name? | Often yes (`'pending'` → `PENDING`) | **No** — `3` is meaningless without context |
| Source fidelity | `var_export` round-trips `'x'` | `1000000` vs `1_000_000` vs `0xF4240` — **must keep the original token** |
| Type | always `string` | `int` or `float`; `const int`/`const float` |
| Noise filtering | shape + role (exception msg, format…) | value semantics: `0`,`1`, loop bounds, array indices, arithmetic operands, bitwise flags |
| Grouping | by value is usually right | by value alone is **wrong**: `100` (percent) vs `100` (buffer size) vs `100` (count) |
| Expression risk | low | high: `60 * 60 * 24` would become `self::SIXTY * self::SIXTY * self::TWENTY_FOUR` |

The pipeline's *shape* (detect/filter/group/name/place/rewrite/SemConv) is
reused; the **strategy inside each step** gains a numeric branch. That is a
classic "extend, don't fork" move, which is exactly why the current ports exist.

---

## 2. Core model change: `LiteralInterface`

Today `StringLiteral implements StringLiteralInterface` (with `value`, `line`,
`role`, `className`, `methodName`, all via property-hook `{ get; }`). Numbers
need the same fields **plus `raw` and a `kind`**, and the pipeline must treat
both uniformly.

```php
interface LiteralInterface
{
    public string $kind { get; }          // LiteralKind::String/Int/Float
    public string $value { get; }         // parsed scalar (string|int|float as string)
    public string $raw { get; }           // EXACT source token: '1_000_000', '0x1F', '3.14'
    public int $line { get; }
    public string $role { get; }
    public string $className { get; }
    public string $methodName { get; }
    public function needle(): string;     // search string for line replacement
}

interface StringLiteralInterface extends LiteralInterface { /* unchanged contract */ }
final readonly class StringLiteral implements StringLiteralInterface { /* + trait */ }
final readonly class NumberLiteral implements LiteralInterface { /* int|float */ }
```

- `StringLiteralInterface extends LiteralInterface` ⇒ **additive, BC-safe** for
  pre-1.0 (no public type removed).
- `needle()`: strings keep `var_export($value, true)` (single-quoted form, as
  today); numbers return `raw` so `1_000_000` and `0x1F` match verbatim in the
  source line. This is the single most important correctness fix for numbers.
- `LiteralKind` is a **backed enum** (closed set). `LiteralRole` stays an
  extensible validated string (it is open-ended) — do **not** promote it to an
  enum.

A small, **stateless** trait `LiteralValueObjectTrait` holds the shared fields
and the `needle()`/role helpers so `StringLiteral` and `NumberLiteral` stay DRY
without hidden state (per `AGENTS.md` trait rules).

---

## 3. Detection: capture numbers + raw tokens

`MagicStringVisitor` already collects `String_` and skips `ClassConst`
children. Extend it (rename to `MagicLiteralVisitor`, keep the old interface
alias) to also collect `Node\Scalar\LNumber` and `Node\Scalar\DNumber`,
recording `raw` from the node's start/end offset in the source (`$node->getStartFilePos()`
… `getEndFilePos()` against the original `$code`). The visitor must **not**
execute anything — it already only reads the AST.

`RoleClassifier::classify()` already handles `Node\Const_` (→ `class_const_value`),
comparison, array key/value, args, branch — these transfer directly to numbers.
Add numeric-specific signals:

- Parent is `Expr\BinaryOp` (arithmetic/bitwise) ⇒ role `numeric_expression`
  (default-excluded: too risky to split an expression).
- Parent is `Expr\UnaryMinus` ⇒ `numeric_negated` (skip for v1; the literal node
  value is the positive magnitude and re-wrapping `-3` needs the whole span).
- Otherwise reuse existing roles.

`Detector` stays a pure `scanSource(string): list<LiteralInterface>`. No
signature change except the return type widens to the supertype.

---

## 4. Naming: context or skip

This is the crux. A value-only name like `NUMBER_3` is noise, so we **require a
naming hint** before promoting a number. Introduce a port:

```php
interface NamingContextExtractorInterface
{
    // Reads variable/parameter/property name, preceding comment/docblock, and
    // unit hints from the AST around the literal. Pure, no execution.
    public function extract(LiteralInterface $lit, Node $node): NamingContext;
}
```

`NamingContext` (value object, property-hook computed fields) carries e.g.
`variableName`, `comment`, `unit`. Then:

```php
interface NumericNameGeneratorInterface
{
    // Returns a UPPER_SNAKE name, or null when no meaningful name is derivable.
    public function generate(NumberLiteral $lit, NamingContext $ctx): ?string;
}
```

Derivation priority (tunable):
1. Unit suffix in the identifier (`$timeoutMs` ⇒ `TIMEOUT_MS`; `$maxRetries` ⇒
   `MAX_RETRIES`).
2. Preceding line comment / docblock (`// seconds` ⇒ append `_SECONDS`).
3. Config/array key context (`['cache.ttl' => 3600]` ⇒ `CACHE_TTL`).
4. Otherwise ⇒ `null` ⇒ filtered out (we do **not** promote nameless numbers).

Strings keep the existing `ConstantNameGenerator` (value → `UPPER_SNAKE`); the
`Disenchanter` dispatches to the string vs numeric name generator by `kind`.

---

## 5. Filtering & grouping for numbers

`CandidateFilter` becomes kind-aware:

- Strings: existing shape + role rules (exception msg, format, enum backing,
  `class_const_value`, identifier-shape).
- Numbers: drop `numeric_expression`, `numeric_negated`, and "trivial" values
  (`0`, `1`, `0.0`, `1.0`, tiny loop bounds) by default; **and** drop any number
  whose `NumericNameGenerator` returned `null` (no hint). The
  `class_const_value` exclusion already protects `private const int X = 3;`.

Grouping (`CandidateGrouper`): strings group by value. Numbers group by
**(value, naming-context signature)** so two `100`s with different derived names
don't collapse. A pluggable `GroupingStrategyInterface` (by-value for strings,
by-value+context for numbers) keeps this clean and testable.

`detect()` currently returns `array<string, CandidateInterface>` keyed by literal
value — but PHP collapses `'3'` and `3` to the same int key, and strings/floats
could collide. **Change the key to a kind-prefixed composite**
(`"s:admin-user"`, `"i:3"`, `"f:3.14"`) so grouping/keys are unambiguous. This is
a pre-1.0 BC note, not a break for a stable release.

---

## 6. Placement, rewrite, SemConv — make them typed & raw-aware

- `ConstantPlacement` gains a `type` (`int`/`float`/`string`) derived from
  `LiteralKind`. The `LineBasedRewriter` currently hardcodes
  `private const string %s = %s;`; switch to
  `private const {type} %s = {value};` using `raw` for the value so
  `private const int MASK = 0x1F;` is preserved verbatim. Its existing-constant
  regex (`\bconst\s+(?:[A-Za-z_][A-Za-z0-9_]*\s+)?([A-Z_][A-Z0-9_]*)\s*=`)
  already tolerates a leading type — no change needed there. The self-rewrap
  guard (idempotency) and `class_const_value` skip already cover numeric consts.
- `SemConvCollector::render()` hardcodes `public const string NAME = ...;`. Make
  the const type dynamic from `LiteralKind` and store a **canonical** value
  (`var_export` of the parsed scalar) so `0x1F` and `31` share one registry
  entry keyed by value; the class declaration keeps `raw` (`0x1F`) and aliases
  `SemConv::MASK`. `SemConvAliasRewriter` already handles an optional type in its
  declaration regex — only its `var_export` value must become kind-aware.
- `rewrite()` / `rewriteWithSemConv()` signatures stay the same; behaviour
  extends transparently.

---

## 7. PHP 8.4 feature leverage (only where it earns its place)

| Feature | Where | Why it fits (not fashion) |
|---|---|---|
| **`WeakMap`** | `Detector` (or a `ParsedSourceCache`) keyed by a `SourceCode` value object | Non-owning cache of AST/scan results; GC'd when the run's `SourceCode` dies. Fixes the current re-parse-per-step waste: hoist one `SourceCode` per `Disenchanter` call and thread it detect→rewrite→SemConv (5 steps) so the parse happens once. Per `AGENTS.md`: "WeakMap caches keyed by a SourceCode value object". |
| **Lazy objects** | parser/traverser/visitor stack inside `Detector` | nikic parser + traverser construction is the expensive bit. Wrap it in a `Lazy` proxy so `Detector` is cheap to build and the stack materialises only on first `scanSource`. "Build the nikic parser once" / lazy-object guidance from `AGENTS.md`. |
| **Property hooks** | `SemConvConfig::fullyQualifiedName` (→ `get` hook), `LiteralInterface::needle()`/`kind`, `ConstantPlacement::declaredType` (→ `get` hook), `NamingContext` computed fields | Replaces small method/getter boilerplate with declarative computed state; no extra stored state. Already used by `StringLiteralInterface`. |
| **Trait** (stateless) | `LiteralValueObjectTrait` shared by `StringLiteral`/`NumberLiteral` | DRY the identical `value/line/role/className/methodName` fields + `needle()` helper without hidden dependencies. Must stay focused & stateless (per `AGENTS.md`). |
| **Enum** | `LiteralKind: string` (String/Int/Float) | Closed, stable set → backed enum. Drives type selection in placement/collector. `LiteralRole` deliberately stays a validated string (open set). |
| **Generators** | `MagicLiteralVisitor` / `Detector::scanSource` streaming variant | For very large trees, yield literals instead of buffering the whole list; bounds memory. Optional; the array-returning port stays the default. |
| **Fibers** | A *consumer-side* runtime adapter (e.g. `Runtime\FiberBatchScanner`) | Overlap file reads (I/O) with parsing across a tree. Core `Disenchanter` stays pure string-in/string-out; fibers live only at the adapter layer, consistent with `AGENTS.md` ("Fibers through runtime adapters"). **Not** in Core. |
| **`WeakReference`** | — | **Not needed.** `WeakMap` covers the only cache case; nothing requires non-owning single-reference semantics. Called out so we don't add it by reflex. |

---

## 8. Refactoring the core (hexagonal alignment)

New / changed ports (all under `Contract\`, depending only on interfaces + VOs):

- `LiteralInterface` (+ `StringLiteralInterface extends …`) — the unified carrier.
- `LiteralDetectorInterface` (widens `StringLiteralDetectorInterface`'s return to
  `list<LiteralInterface>`; keep the old interface as an alias for BC).
- `NamingContextExtractorInterface` + `NamingContext` VO.
- `NumericNameGeneratorInterface` (strings keep `ConstantNameGeneratorInterface`).
- `GroupingStrategyInterface` (by-value / by-value+context).
- `LiteralKind` enum (ValueObject).

`ValueObject` gains: `NumberLiteral`, `NamingContext`, `LiteralKind`,
`SourceCode` (the cache key), and `ConstantPlacement` gains `type`.

`Factory` wiring: `DetectorFactory` builds the `WeakMap`-backed detector + lazy
parser + `MagicLiteralVisitor`; `ConstantNameGeneratorFactory` (new) assembles
the string generator + `NumericNameGenerator` + `NamingContextExtractor`;
`DisenchanterFactory` dispatches name generation by `kind`. PHPArkitect rules
continue to enforce Contract → nothing; Core → Contract/VO; Adapter → Contract
only; `ItPack\RunIt\*` never imported by Core.

---

## 9. Open questions / decisions needed

1. **Skip-without-hint is the default rule?** Recommend *yes*: never promote a
   number we cannot name. Alternative: promote with a `MAGIC_INT_<value>` name +
   a `// TODO: rename` — but that adds noise the hard rule tries to avoid.
2. **Arithmetic operands** (`60 * 60`): skip entirely for v1 (role
   `numeric_expression`). A future "whole-expression promotion" (`private const
   int SECONDS_PER_DAY = 60 * 60 * 24;`) is attractive but needs expression-span
   replacement — defer.
3. **Negative numbers / floats**: skip `UnaryMinus`-wrapped for v1; floats
   promoted only when named (rates, `PI`). Scientific/`NAN`/`INF` excluded.
4. **`detect()` key change** to kind-prefixed composite keys — accept as a
   pre-1.0 adjustment? Recommend yes; document in the ADR.
5. **Registry value canonicalisation**: store canonical (decimal) value in
   SemConv while class decls keep `raw`. Acceptable since `SemConv::MASK`
   aliases to an equal value. Confirm no consumer depends on raw form in the
   registry.

---

## 10. Incremental rollout & tests

Phased, each behind its own tests (Codeception, per `AGENTS.md`):

- **P1 — model + detection**: `LiteralInterface`, `NumberLiteral`,
  `MagicLiteralVisitor` numeric capture + `raw`. Tests: raw fidelity
  (`1_000_000`, `0x1F`, `3.14`), `class_const_value` guard for numeric consts.
- **P2 — filter/name/group**: `NumericNameGenerator` + `NamingContextExtractor`,
  numeric filter rules, context grouping. Tests: no-hint ⇒ excluded;
  `$timeoutMs = 30000` ⇒ `TIMEOUT_MS`; two `100`s with different hints stay
  separate.
- **P3 — rewrite/SemConv typed**: `ConstantPlacement::type`, rewriter raw/typed
  decls, collector type-aware. Tests: `private const int MASK = 0x1F;`, SemConv
  `public const int MASK = 31;`, idempotent re-run, no self-rewrap, no duplicate
  const.
- **P4 — core perf refactor**: `WeakMap` cache + `SourceCode` threading, lazy
  parser proxy, generators. Tests: same `SourceCode` ⇒ one parse; lazy proxy not
  materialised until first scan; performance benchmark (scan throughput) — no
  claimed improvement without measurements (per `AGENTS.md`).

Each phase preserves the source-text-safety hard rule (no execute/autoload),
idempotency, and the existing public `DisenchanterInterface` contract.

---

## 11. Risks

- **Naming quality** is the make-or-break: weak names make the feature worse
  than nothing → the no-hint-skip rule is the safety net.
- **Raw-token fidelity** bugs (off-by-one in start/end offsets, PHP 8.4
  `raw`/`startFilePos` quirks) would corrupt lines → covered by P1 fidelity
  tests.
- **Key-collision** between string/float/int values → mitigated by P4 composite
  keys.
- **SemConv type drift** (registry canonical vs class raw) → mitigated by P3
  equality tests.
