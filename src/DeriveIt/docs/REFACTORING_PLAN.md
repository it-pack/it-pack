# DeriveIt — Refactoring Plan (REVIEW.md F-1 … F-10)

> Companion to `REVIEW.md` and `ROADMAP.md` (Phase 12).
> Prepared: 2026-08-22 · Package: `it-pack/derive-it` · Scope: residual polish from
> the post-Phase-11 review (`REVIEW.md` §11, findings F-1 … F-10).
>
> **Hard rule:** every step must keep all five quality gates green
> (`test:unit`, `cs:check`, `analyse`, `architecture`, `infection ≥ 80%`) per
> `AGENTS.md`. No user-visible derivation-output change is intended; the only
> API surface changes are *additions* (new interfaces/constants) plus internal
> de-duplication. Where a step is purely optional it is marked ◐.

## Severity legend (mirrors REVIEW.md §11)

- **Medium** — should fix soon / clear quality debt (F-1, F-2).
- **Low** — polish / optional (F-3 … F-10).

## Finding → plan-step map

| Finding | Severity | Plan step | Theme |
|---------|----------|-----------|-------|
| F-1 | Medium | RP-1 | Magic numbers → named exit-code constants (`DeriveCommand`) |
| F-2 | Medium | RP-2 | Magic strings `'void'`/`'\|'`/`'&'` → named constants (`AdapterGenerator`) |
| F-3 | Low ◐ | RP-3 | Optional `MethodSignatureFactory` / `SourceRenderer` interfaces (DIP seam) |
| F-4 | Low ◐ | RP-4 | Optional `CommandInterface` for `DeriveCommand` |
| F-5 | Low | RP-5 | `Naming::shortName()` to de-duplicate FQCN→short-name surgery |
| F-6 | Low | RP-6 | Default `create()` body in `LazyCreatable` (drop resolver re-declarations) |
| F-7 | Low ◐ | RP-7 | Optional `WeakReference` reflection cache for long-running scans |
| F-8 | Low | RP-8 | `FamilyDeriver` perf micro-fixes (queue / config identity) |
| F-9 | Low ◐ | RP-9 | Optional `--out` path traversal / symlink guard |
| F-10 | Low | RP-10 | Route `DeriveIt` facade through `DeriveItFactory` |

**Sequencing (quick wins → optional seams):**

```
RP-1, RP-2          (Medium: magic literals → constants)
   └─ RP-5, RP-6   (Low DRY: Naming::shortName, LazyCreatable default)
        └─ RP-8    (Low perf: FamilyDeriver queue/config identity)
             └─ RP-10  (Low consistency: facade → factory)
                  └─ RP-3, RP-4, RP-7, RP-9   (optional seams / defense-in-depth)
```

---

## RP-1 — Named exit-code constants (F-1, Medium)

**Location:** `src/Cli/DeriveCommand.php` (`execute()` returns `0`/`1`/`2`/`3` at
lines 60, 69, 78, 93, 101, 282, 294, 303).

**Why:** process exit codes are a public CLI contract but appear as bare literals,
so a reader cannot tell which code means what and edits risk silent collisions.

**Goal:** replace every literal with a named `private const` and reference them.

### Steps
1. Add the following constants to `DeriveCommand` (near `OUTPUT_DIR_MODE`):
   ```php
   private const EXIT_SUCCESS    = 0;
   private const EXIT_USAGE      = 1;
   private const EXIT_DERIVATION = 2;
   private const EXIT_OUTPUT     = 3;
   ```
2. Replace each `return <n>;` in `execute()` with the matching constant:
   - `return 1;` (no args / `--help` with no args, and the `InvalidArgumentException`
     from `parse()`) → `self::EXIT_USAGE`.
   - `return 2;` (resolution / derivation `catch` blocks) → `self::EXIT_DERIVATION`.
   - `return 3;` (output `catch` block) → `self::EXIT_OUTPUT`.
   - `return 0;` (success branches in `execute()` and `emit()`) → `self::EXIT_SUCCESS`.
3. Update the class docblock's "Exit codes:" line to reference the constant names.

### Acceptance
- `grep -nE 'return [0-3];' src/Cli/DeriveCommand.php` returns **no** matches.
- `composer cs:check` / `analyse` clean; `DeriveCommandTest` (13 tests) still green;
  exit-code behavior unchanged (assert the numeric value is identical in tests).

### Risk / rollback
None — pure rename. If a test asserts a literal `1`/`2`/`3`, update it to the
constant's value (the number is unchanged).

---

## RP-2 — Named type-separator constants (F-2, Medium)

**Location:** `src/Generator/AdapterGenerator.php::bodyFor()` (lines 137, 141–143,
146, 156, 173) parses the **string** return type with literal `'void'`, `'|'`,
`'&'`.

**Why:** the rendered return type already arrives as a string from Nette
(`Method::getReturnType()`); re-parsing it with bare separators is correct but
fragile to read and brittle to future parsing changes.

**Goal:** hoist the three literals into named constants. (Threading the original
`ReflectionType`/`ReflectionMethod` through would be a larger change; it is noted
as an optional alternative below and is **out of scope** for this step to keep the
generated output byte-identical.)

### Steps
1. Add constants to `AdapterGenerator`:
   ```php
   private const TYPE_VOID          = 'void';
   private const TYPE_SEPARATOR_OR  = '|';
   private const TYPE_SEPARATOR_AND = '&';
   ```
2. In `bodyFor()`:
   - `if ($returnType === null || $returnType === 'void')` →
     `... === self::TYPE_VOID`.
   - `$isUnion = str_contains($normalized, '|');` →
     `str_contains($normalized, self::TYPE_SEPARATOR_OR)`.
   - `$isIntersection = str_contains($normalized, '&');` →
     `str_contains($normalized, self::TYPE_SEPARATOR_AND)`.
   - `$segments = preg_split('/[|&]/', $normalized);` →
     `preg_split('/[' . self::TYPE_SEPARATOR_OR . self::TYPE_SEPARATOR_AND . ']/', $normalized)`.
3. Add `MethodSignatureFactory::filterAttributes` hard-codes `'Override'`
   (F-2 spillover in REVIEW §6): add
   `private const OVERRIDE_ATTRIBUTE = 'Override';` and use it in the `=== 'Override'`
   comparison (keep the `ltrim($name, '\\')` normalization).

### Acceptance
- No bare `'void'` / `'|'` / `'&'` / `'Override'` string literals remain in
  `AdapterGenerator` / `MethodSignatureFactory` source outside the constant
  declarations.
- Golden file `tests/_expected/Tree.adapter.php` is byte-identical; `AdapterGeneratorTest`
  green; `infection` ≥ 80%.

### Optional alternative (not in this step)
Thread `ReflectionMethod` (or its `ReflectionType`) into `AdapterGenerator` so
`bodyFor` branches on a real type object instead of a re-parsed string. Larger,
risks output drift — defer unless a future feature needs the structured type.

---

## RP-3 — Optional `MethodSignatureFactory` / `SourceRenderer` ports (F-3, Low ◐)

**Location:** `src/Generator/MethodSignatureFactory.php`, `src/Generator/SourceRenderer.php`
are concrete collaborators of the derivers (no interface).

**Why:** the hexagonal model is already satisfied at swap-relevant boundaries;
finer-grained DI/testing of the derivers (e.g. substituting a fake signature
factory) would require ports. This is **not** required for correctness.

**Goal:** optionally extract two one-method interfaces and depend on them
(still defaulting to the current concretions), so the derivers are fully
substitutable end-to-end.

### Steps
1. New `src/Contract/Generator/MethodSignatureFactoryInterface.php`:
   `fromMethod(ReflectionMethod $method, ?TypeSubstitutor $substitutor = null): Method`.
2. New `src/Contract/Generator/SourceRendererInterface.php`:
   `render(InterfaceType $interface, string $namespace): string` and
   `renderMany(array $interfaces, string $namespace): string`.
3. `MethodSignatureFactory` / `SourceRenderer` `implements` their new interfaces.
4. `InterfaceDeriver` / `PerTraitInterfaceDeriver` / `FamilyDeriver` accept the
   interface type (defaulting to `new MethodSignatureFactory(...)` / `new SourceRenderer()`
   in the constructor, exactly as today) so behavior is unchanged.
5. Update `DeriveItFactory` to type-hint the interfaces when it injects them.

### Acceptance
- `architecture` (deptrac) 0 violations — the new contracts go in `Contract`,
  which depends on nothing; `Generator` already may use `Contract`.
- No behavioral change: golden files byte-identical; all gates green.

### Risk / rollback
Low. Keep the concrete constructors as the default so external callers are
unaffected. Skip entirely if finer DI is never needed (F-3 is optional).

---

## RP-4 — Optional `CommandInterface` (F-4, Low ◐)

**Location:** `src/Cli/DeriveCommand.php` has no port.

**Why:** the command is a single CLI entry point; an interface is optional but
would make it injectable/testable behind a boundary.

### Steps
1. New `src/Contract/Cli/CommandInterface.php` with
   `execute(array $args): int` (the only public method today).
2. `DeriveCommand implements CommandInterface`.
3. (Optional) Route `bin/derive` to resolve the command via the interface.

### Acceptance
- `architecture` 0 violations (`Contract` may be depended on by `Cli`).
- `DeriveCommandTest` green; no behavior change.

### Risk / rollback
None. Pure addition.

---

## RP-5 — `Naming::shortName()` DRY helper (F-5, Low)

**Location:** FQCN→short-name substring surgery
`substr($fqcn, (int) strrpos($fqcn, '\\') + 1)` is duplicated in
`DeriveCommand::fileNameFor()` (`src/Cli/DeriveCommand.php:308`),
`PerTraitInterfaceDeriver::assertPerTraitSelfContained`, `FamilyDeriver` constant
handling, and `LeakageAuditor::allowedNamespaces` (per REVIEW §4).

**Why:** four copies of the same string surgery drift and are easy to get subtly
wrong (the `int` cast, the `+1` offset).

**Goal:** centralize the logic in `Naming::shortName()`.

### Steps
1. Add to `src/Generator/Naming.php`:
   ```php
   public static function shortName(string $fqcn): string
   {
       $pos = strrpos($fqcn, '\\');

       return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
   }
   ```
2. Replace the inline `substr(..., strrpos(..., '\\') + 1)` calls in each of the
   four sites with `Naming::shortName($fqcn)`. In `DeriveCommand::fileNameFor` the
   resulting short name then feeds the existing `preg_replace` sanitizer.
3. Verify `LeakageAuditor::allowedNamespaces` and `FamilyDeriver` still compute
   identical short names (they operate on FQCNs with a namespace separator, same as
   `getShortName()`).

### Acceptance
- `grep -rnE "strr?pos\(\\\$[a-zA-Z_]+, '\\\\\\\\'" src/` finds no remaining inline
  FQCN→short-name surgery outside `Naming`.
- All gates green; `DeriveCommandTest` and the per-trait / leakage / family tests pass.

### Risk / rollback
Low. Behaviorally identical for any FQCN containing a `\`; global-namespace FQCNs
(`strrpos === false`) return unchanged.

---

## RP-6 — Default `create()` in `LazyCreatable` (F-6, Low)

**Location:** `src/Behavior/Utility/LazyCreatable.php` declares
`abstract public static function create(): self;`. Each of the three resolvers
re-declares the identical body `return new self();`:
`src/Resolver/RecursiveClassResolver.php:15-17`,
`src/Resolver/RecursiveInterfaceResolver.php:15-17`,
`src/Resolver/RecursiveTraitResolver.php:24-26`.

**Why:** the resolver bodies are byte-identical; the `abstract` forces every user
to re-state them.

**Goal:** supply a default `create()` body in the trait so users that need no
custom construction simply omit it.

### Steps
1. Replace the `abstract` declaration with a concrete method:
   ```php
   public static function create(): self
   {
       return new self();
   }
   ```
   (`new self()` inside a trait resolves to the class that uses the trait, so the
   returned instance has the correct concrete type.)
2. Delete the three duplicated `public static function create(): self { return new self(); }`
   blocks from the resolvers. Their behavior is now inherited from the trait.
3. Confirm no resolver relies on a customized `create()` body (none do today).

### Acceptance
- `grep -rn "function create" src/Resolver` shows no resolver-level `create()`
  definitions (only the trait provides it).
- `ResolverTest`(s) and `ClassReflectionTest` still build resolvers via `create()`;
  all gates green.

### Risk / rollback
Low. `self` in a trait binds to the using class; identical to the removed code.

---

## RP-7 — Optional `WeakReference` reflection cache (F-7, Low ◐)

**Location:** `src/Reflection/InMemoryReflectionCache.php` holds
`array<string, ReflectionClass>` for the cache's lifetime.

**Why:** for whole-repo scans in a long-running process, keeping every
`ReflectionClass` alive may retain metadata longer than needed. A `WeakReference`
lets entries be GC'd and re-reflected on demand (deterministic, since reflection is
side-effect-free).

**Goal:** offer an optional cache variant that stores `WeakReference` values, so a
caller doing a large scan can opt in without changing the default.

### Steps
1. New `src/Reflection/WeakReflectionCache.php` implementing
   `ReflectionCacheInterface`:
   - `get()` returns `$ref->get()` (null if the target was GC'd).
   - `set()` stores `WeakReference::create($reflection)`.
2. Keep `InMemoryReflectionCache` as the default (instance-scoped, no behavior
   change). Expose the new cache only via `DeriveItFactory` / deriver constructor
   for callers that want it.
3. Add a small test asserting `get()` returns the reflection while alive and `null`
   after `gc_collect_cycles()` when all other references are dropped.

### Acceptance
- New file compiles; `architecture` 0 violations (`Reflection` → `Contract` only).
- Default path unchanged; golden files byte-identical.

### Risk / rollback
Low, **optional**. `WeakReference` requires PHP ≥ 7.4 (satisfied). Skip if no
long-running scan use case materializes (F-7 is optional).

---

## RP-8 — `FamilyDeriver` perf micro-fixes (F-8, Low)

**Location:** `src/Generator/FamilyDeriver.php` — `discover()` uses `array_shift()`
in a `while` loop (O(n²) dequeue, line 243); `configHash()` re-`serialize()`s the
full map + config on every derive (line 228); `LeakageAuditor::simplifyDocblock()`
loops the full substitution map per method when docblocks are preserved.

**Why:** all three are negligible at current family sizes; they are flagged as
micro-optimizations only if profiling ever shows pressure.

**Goal:** apply the safe, behavior-preserving queue/index fix; treat the other two
as measure-first (no change unless a benchmark justifies it).

### Steps
1. **Queue (safe, do now):** replace `array_shift($queue)` in `discover()` with an
   index pointer:
   ```php
   $i = 0;
   while ($i < count($queue)) {
       $reflection = $queue[$i++];
       // … existing body unchanged …
   }
   ```
   (Keeps `[]=` appends; removes the O(n²) shift. Output identical.)
2. **Config identity (measure first):** if a benchmark shows `configHash` is hot,
   key the memo by a stable identity (e.g. `spl_object_id($this->config)` combined
   with the map hash) rather than re-serializing the whole `DeriveConfig`. Do **not**
   change the cache key format unless a test pins it; otherwise keep `md5(serialize(...))`.
3. **Docblock simplification (measure first):** leave as-is unless profiling of
   `preserveDocblocks` large families shows the per-method map loop dominating.

### Acceptance
- After step 1: `family` / `family-adapter` golden + unit tests byte-identical and
  green; `infection` ≥ 80%.
- Steps 2–3 are gated on a benchmark; if skipped, note "no change needed" in the
  commit.

### Risk / rollback
Step 1 is trivially reversible. Steps 2–3 intentionally deferred to evidence.

---

## RP-9 — Optional `--out` path guard (F-9, Low ◐)

**Location:** `src/Cli/DeriveCommand.php::emit()` / `writeFile()` write to an
operator-supplied `--out` path (`file_put_contents`, `mkdir(..., OUTPUT_DIR_MODE, true)`).

**Why:** covered today by the documented trust model (the CLI runs only on trusted
code, R.13). A defense-in-depth check would tighten it without hurting DX.

**Goal:** reject path traversal / symlink escapes when an optional base directory
is supplied; otherwise keep current behavior.

### Steps
1. Add a `private function assertSafeOutputPath(string $path, ?string $baseDir): void`
   that, when `$baseDir` is set, resolves both `$path` and `$baseDir` via
   `realpath()`/`FileSystem::readlink` semantics and throws `InvalidArgumentException`
   if the resolved target is not within `$baseDir` or if an intermediate component
   is a symlink escape.
2. Wire it into `emit()` before the `mkdir`/`writeFile` calls; gate it so that
   **without** a base dir the current (trusted-operator) behavior is preserved.
3. Add `--base-dir=DIR` (optional) to `ArgParser` option table and thread it
   through `DeriveCliInput`.

### Acceptance
- Existing `DeriveCommandTest` (no base dir) still green; new tests assert that a
  traversal path under a base dir is rejected and a safe path is allowed.
- No change for the default trusted-operator flow.

### Risk / rollback
Low, **optional**. Must not break the documented "write anywhere the operator
points" behavior when `--base-dir` is absent.

---

## RP-10 — Route `DeriveIt` facade through `DeriveItFactory` (F-10, Low)

**Location:** `src/DeriveIt.php` — every static method `new`s concrete derivers
(`new InterfaceDeriver(...)`, `new FamilyDeriver($config)`, `new AdapterGenerator(...)`,
`new PerTraitInterfaceDeriver(...)`, `new SourceRenderer()`), duplicating the
assembly `DeriveItFactory` exists to centralize (`src/DeriveItFactory.php`).

**Why:** the facade and the factory overlap; the factory is the documented
composition root, so the facade should reuse it (or explicitly document itself as
an intentional thin wrapper — documentation-only alternative, see note).

**Goal:** obtain deriver instances from a lazily-built `DeriveItFactory` so the
assembly logic lives in exactly one place, keeping all current signatures.

### Steps
1. Add a private static accessor to `DeriveIt`:
   ```php
   private static function factory(DeriveConfig $config): DeriveItFactory
   {
       return new DeriveItFactory($config);
   }
   ```
   (A per-call factory is fine — it is stateless and cheap; if a shared instance is
   desired later, memoize it in a `static` field *only* if profiled, but that
   reintroduces the static-state smell the Phase 8 hardening removed, so prefer
   per-call.)
2. Rewrite each static method to use the factory:
   - `derive()` / `deriveInterface()` / `deriveWithResult()` / `analyze()` /
     `diff()` → `self::factory($config)->interfaceDeriver()->deriveSource/derive/...`.
   - `derivePerTrait()` / `deriveTraitInterfaces()` →
     `self::factory($config)->perTraitDeriver()->...`.
   - `deriveFamily()` / `deriveFamilySource()` / `deriveFamilyFromRoot()` →
     `self::factory($config)->familyDeriver()->...`, then render via
     `SourceRenderer` (still one shared helper).
   - `deriveAdapter()` / `deriveFamilyAdapter()` → build the interface via the
     factory's `interfaceDeriver()`, then `factory($config)->adapterGenerator(...)`.
3. Keep `new SourceRenderer()` (it is a stateless value renderer) or thread a single
   `SourceRenderer` from the factory — either is acceptable; prefer the factory for
   consistency.
4. **Alternative (documentation-only, if routing is unwanted):** add a one-line doc
   note to `DeriveIt` stating it is an intentional thin convenience wrapper that
   assembles derivers directly for zero-ceremony use, and that `DeriveItFactory` is
   the injectable composition root. Choose routing (preferred) **or** this note, not
   both.

### Acceptance
- `grep -nE "new (InterfaceDeriver|FamilyDeriver|AdapterGenerator|PerTraitInterfaceDeriver)\b" src/DeriveIt.php`
  returns no matches (factory supplies them).
- All `DeriveIt*` facade tests and golden files byte-identical; all five gates green.

### Risk / rollback
Low. Signatures unchanged; only the object source changes. The `DeriveItFactory`
already constructs the identical object graph.

---

## Definition of Done

- [ ] RP-1: `DeriveCommand` exit codes are named constants; no bare `0/1/2/3` returns.
- [ ] RP-2: `AdapterGenerator` / `MethodSignatureFactory` type literals are constants;
      golden adapter file byte-identical.
- [ ] RP-5: `Naming::shortName()` exists and the four inline copies are gone.
- [ ] RP-6: `LazyCreatable` supplies `create()`; resolver bodies deleted.
- [ ] RP-8: `FamilyDeriver::discover` uses an index-pointer queue; output unchanged.
- [ ] RP-10: `DeriveIt` obtains derivers via `DeriveItFactory`.
- [ ] RP-3, RP-4, RP-7, RP-9: implemented **or** explicitly marked optional/skipped
      with a one-line rationale in the commit.
- [ ] All five gates green: `test:unit`, `cs:check`, `analyse`, `architecture`,
      `infection` MSI ≥ 80%.
- [ ] `REVIEW.md` §11 findings F-1 … F-10 each carry an "addressed / optional / N-A"
      resolution note.

*This plan is the detailed execution spec for ROADMAP.md Phase 12.*
