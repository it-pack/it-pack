# ConfigureIt — Refactoring & Optimization Plan

**Companion to:** `docs/REVIEW.md` (findings R1–R18) and `docs/OPTIMIZATION_ROADMAP.md`
(phases 0–5, already merged). This document turns the *open* findings from `REVIEW.md`
into an ordered, verifiable set of refactoring milestones. Resolved findings (R1–R6) are
not repeated here except as a traceability note.

**Target:** `php: ^8.5`. Gates are PHPStan **level 9** + ShipMonk dead-code, cs-fixer
(`@PSR12` + `@PHP84Migration` + `no_superfluous_elseif`), PHPUnit.

> **Composer script names** (use these exact names in every step):
> - `composer analyse`  → PHPStan level 9 + ShipMonk dead-code
> - `composer cs:check` → `php-cs-fixer fix --dry-run --diff`
> - `composer test:unit` → Codeception Unit suite (PHPUnit)

**Branch strategy:** one branch per milestone so each can be reviewed/merged independently:
`refactor/discriminators`, `refactor/dip-purity`, `refactor/hygiene`, `refactor/dry-opt`.
Run the full gate suite after every step, not just at the end.

**Assumptions**
- Only the *open* findings are in scope; intentional non-adoption (R14 property hooks,
  R15 WeakMap, R16 fibers) is recorded in the final section and is **not** scheduled.
- Mechanical edits (enum cases, `stageName()` returns, docblock cleanup) keep the
  serialized-cache wire format stable because getters/serialization use `->value`.
- `mixed` for heterogeneous config trees stays (domain necessity, per `AGENTS.md`).

---

## Phase 0 — Pre-flight (run once)

```bash
cd src/ConfigureIt
composer install --no-interaction
composer analyse     # baseline must be green (PHPStan L9)
composer cs:check    # baseline must be clean
composer test:unit   # baseline must be green
git -C /home/quill/tinker/php/project/it-pack log --oneline -3
```

| Gate | Result (expected) |
|------|-------------------|
| `composer analyse` | **0 errors** |
| `composer cs:check` | **0 of 260 files** |
| `composer test:unit` | **267 tests / 620 assertions** |

Keep `vendor/` untouched — the `nette/schema` LSP noise is third-party and out of scope.

---

## Milestone 1 — Enum-backed discriminators (R9 + R10a)

**Goal:** complete the enum migration for pipeline stage names and replace the
`get_class($schema)` discriminator with the `ProcessorStage` enum. This removes the last
cluster of magic stage strings and the one remaining dynamic-class discriminator used as a
domain identity.

**Files:** `src/ValueObject/ProcessorStage.php`, `src/Core/ValidationProcessor.php`,
`src/Core/CastingProcessor.php`, `src/Core/IncludeProcessor.php`,
`src/Core/VariableProcessor.php`, `src/Core/DefaultsProcessor.php` (already enum-backed).

### Tasks

1. **Extend `ProcessorStage`** with the remaining stage names. Add cases that match the
   current `stageName()` literals exactly (so the wire format is unchanged):
   ```php
   enum ProcessorStage: string
   {
       case Defaults   = 'DefaultsProcessor';
       case Validation = 'ValidationProcessor';
       case Casting    = 'CastingProcessor';
       case Include    = 'IncludeProcessor';
       case Variable   = 'VariableProcessor';

       public static function fromString(string $value): self
       {
           return self::tryFrom($value) ?? self::Defaults;
       }
   }
   ```
   `fromString` already maps unknown → `Defaults`; keep it.

2. **Point each processor's `stageName()` at the enum.** For `ValidationProcessor`,
   `CastingProcessor`, `IncludeProcessor`, `VariableProcessor`, replace the literal with the
   enum value:
   ```php
   public function stageName(): string
   {
       return ProcessorStage::Validation->value;   // etc. per processor
   }
   ```
   Add `use ItPack\ConfigureIt\ValueObject\ProcessorStage;` to each. (`DefaultsProcessor`
   already returns `ProcessorStage::Defaults->value` from Phase 4 — verify, do not regress.)

3. **Replace `get_class($schema)` in `ValidationProcessor`.** Currently:
   ```php
   $stage = get_class($this->schema);
   $validated = new ConfigurationGraph();
   foreach ($graph->nodes() as $node) {
       $validated->put($node->withProvenance($node->provenance()->validated($stage)));
   }
   ```
   Change `$stage` to `ProcessorStage::Validation->value` (the schema stage is always the
   Validation stage). Import `ProcessorStage`.

4. **(Optional, larger — R10b)** Loader identity in `ConfigurationResolver::resolveResources()`
   uses `get_class($loader)` as the document adapter. If desired, add
   `ConfigurationLoaderInterface::name(): string` (returning a stable id, e.g. the class
   basename) and implement it on every loader, then use `$loader->name()` instead of
   `get_class($loader)`. **Defer** unless a non-class-name identity is wanted; the class name
   is acceptable provenance metadata and this touches 11 loaders, so it is flagged optional.

### Verification
- `composer analyse` (enum types tighten everything — expect no new errors; double-check the
  four `stageName()` returns and the `ValidationProcessor` `$stage` change).
- `composer cs:check` clean.
- `composer test:unit` green; add/extend a test asserting
  `ValidationProcessor::stageName() === 'ValidationProcessor'` and that a validated node's
  `validatedBy()` equals `'ValidationProcessor'` (round-trip preserved).

### Risk
**Low.** Behavior-preserving; the enum `value` strings equal the previous literals, so cache
artifacts and processor trails are unchanged. The optional R10b loader change is the only
non-trivial sub-task.

### Status
✅ done (2026-08-24). `ProcessorStage` gained `Validation`/`Casting`/`Include`/`Variable`;
all four `ProvenanceAwareProcessorInterface::stageName()` implementations return the enum
`->value`; `ValidationProcessor` no longer calls `get_class($schema)`. Two new assertions in
`ValidationProcessorTest` lock `stageName()` and `validatedBy()`. `composer analyse` 0 errors,
`composer cs:check` 0/260, `composer test:unit` 269 tests / 622 assertions. (R10b loader
identity deferred as optional.)

---

## Milestone 2 — DIP purity in the composition root (R7 + R8)

**Goal:** remove the two remaining DIP slips — `ConfigurationResolver` depending on the
concrete `ConfigurationPipeline`, and `ConfigurationBuilder` instantiating `Execution`
concretes (`ResolutionReporter`, `ExecutionContext`) directly. (The `CachingResolver` /
`ConfigurationCompiler` / `LayeredSource` instantiations in the builder are Core-internal and
are explicitly **acceptable**; only the Execution-namespace concretes are moved out.)

**Files:** `src/Core/ConfigurationResolver.php`, `src/Core/ConfigurationBuilder.php`,
`src/Wiring/DefaultConfigurationWiring.php`,
`src/Contract/Wiring/ConfigurationWiringInterface.php`.

### Tasks

1. **R7 — resolver depends on the pipeline interface.** In `ConfigurationResolver`:
   - Change the constructor param `private ConfigurationPipeline $pipeline` →
     `private ConfigurationPipelineInterface $pipeline` and import
     `ItPack\ConfigureIt\Contract\ConfigurationPipelineInterface`.
   - `ConfigurationFactory::build()` already passes `new ConfigurationPipeline(...)`, which
     implements the interface, so no caller change is required.

2. **R8 — move Execution concretions into the wiring.** Add two methods to
   `ConfigurationWiringInterface` and implement them in `DefaultConfigurationWiring`:
   ```php
   public function baseReporter(): ResolutionReporterInterface;
   public function baseExecutionContext(): ExecutionContextInterface;
   ```
   with bodies `return new ResolutionReporter();` / `return new ExecutionContext();`
   (import the Execution concretes in the wiring only).

3. **Update `ConfigurationBuilder::build()`** to stop `new`-ing Execution types:
   - Replace `new ResolutionReporter()` with
     `$reporter ??= $this->wiring->baseReporter();`
   - In `resolveSources()`, replace `new ExecutionContext()` with
     `$this->executionContext ?? $this->wiring->baseExecutionContext();`
   - Import the two interfaces if not already present (`ResolutionReporterInterface`,
     `ExecutionContextInterface` are already imported in the builder).

4. **(Optional — R8 completeness)** If full purity is wanted, also add
   `ConfigurationWiringInterface::composeCachingResolver(ConfigurationResolverInterface $inner, ConfigurationCacheInterface $cache, ConfigurationCompiler $compiler, array $defaultSources, ?ConfigurationSchemaInterface $schema, array $processors, array $readers, array $loaders, ?ExecutionContextInterface $executionContext): ConfigurationResolverInterface`
   and have the builder delegate `new CachingResolver(...)` to it. `CachingResolver` and
   `ConfigurationCompiler` are Core, so this is optional; leaving them is acceptable.

### Verification
- `composer analyse` (interface substitution is covariant-safe; no new errors expected).
- `composer cs:check` clean.
- `composer test:unit` green; add a test that `ConfigurationResolver` accepts a
  `ConfigurationPipelineInterface` test double (already true via the contract, but assert to
  lock the behavior).

### Risk
**Low–Medium.** Small surface; the wiring becomes the sole `new`-site for Execution concretes,
matching the Phase-2 composition-root pattern. The optional R8 caching step is the only
moderate sub-task.

### Status
✅ done (2026-08-25). **R7** — `ConfigurationResolver` depends on
`ConfigurationPipelineInterface` (constructor param typed
`private ConfigurationPipelineInterface $pipeline`); `ConfigurationFactory::build()` still
passes the concrete `ConfigurationPipeline`, which satisfies the contract, so no caller change
was needed. **R8** — `ConfigurationBuilder::build()` no longer `new`s the `Execution` concretes:
`new ResolutionReporter()` → `$this->wiring->baseReporter()` and `new ExecutionContext()` (in
`resolveSources()`) → `$this->wiring->baseExecutionContext()`; both methods added to
`ConfigurationWiringInterface` and implemented in `DefaultConfigurationWiring` (the only `new`-site
for `ExecutionContext` / `ResolutionReporter`). The optional R8 completeness step was also taken:
`CachingResolver` + `ConfigurationCompiler` construction moved into the wiring via a new
`cachingResolver()` method, so the builder is now a pure collector of configuration. Gates:
`composer analyse` 0 errors (PHPStan L9), `composer cs:check` 0/260,
`composer test:unit` 271 tests / 625 assertions. The behavior is locked by
`ConfigurationResolverTest::testAcceptsPipelineInterfaceImplementation` and
`DefaultConfigurationWiringTest::testCachingResolverWrapsInnerResolverInCachingResolver`.

---

## Milestone 3 — Hygiene & error visibility (R11, R12, R13)

**Goal:** clear the small code-hygiene findings and make the best-effort cache write
observable instead of silently swallowed.

**Files:** `src/Core/CastingProcessor.php`, `src/Core/CachingResolver.php`,
`src/Adapter/FileModificationProbe.php`, `src/ValueObject/DiagnosticsReport.php`,
`src/Cache/FileSystemCache.php`.

### Tasks

1. **R11 — remove the duplicate docblock in `CastingProcessor::cast()`.** The method currently
   has a stray `/** @return int|float|bool|string */` docblock immediately above its real
   `/** @param mixed $value … */` docblock. Delete the empty `@return` docblock so there is a
   single docblock.

2. **R12 — surface cache-write failures.** `CachingResolver::resolve()` wraps
   `$this->cache->set(...)` in `catch (\Throwable) { /* ignore */ }`. Make the failure
   observable:
   - Add `private ?ResolutionReporterInterface $reporter` to `CachingResolver` (import
     `ResolutionReporterInterface`, `Observation`, `Severity`) and a constructor parameter
     defaulting to `null`.
   - In `build()` (the builder), pass the composed `$reporter` into `new CachingResolver(..., reporter: $reporter)`.
   - In the `catch`, replace the empty body with
     `$this->reporter?->observe(new Observation(Severity::Warning, 'Cache write failed: ' . $e->getMessage()));`
   - Keep the snapshot returned regardless (best-effort semantics preserved).

3. **R13 — eliminate the 3 `else` blocks** (honor the no-`else` guideline):
   - `FileModificationProbe::signature()`: replace the `if/else` with an early branch:
     ```php
     foreach ($this->paths as $path) {
         $parts[] = is_file($path)
             ? $path . ':' . md5_file($path)
             : $path . ':missing';
     }
     ```
   - `DiagnosticsReport::__toString()` (overrides section): early-return a prebuilt line when
     `$this->overrides === []` before the `foreach`, instead of `if/else`.
   - `FileSystemCache::toPayload()` (TypedSnapshot branch): assign `$payload` via a
     `match (true)` / ternary on `instanceof TypedSnapshot` rather than `if/else`.

### Verification
- `composer analyse` (the `CachingResolver` reporter param is optional/nullable; no new errors).
- `composer cs:check` clean.
- `composer test:unit` green; add a test that a `CachingResolver` with a throwing cache still
   returns the live snapshot and (when given a reporter) records a `warning` observation.

### Risk
**Low.** All three are local, behavior-preserving; the cache test is the only new assertion.

### Status
✅ done (2026-08-25). **R11** — removed the duplicate `@return` docblock in
`CastingProcessor::cast()` (single docblock now). **R12** — `CachingResolver` gained an
optional `?ResolutionReporterInterface $reporter` constructor param; a throwing
`ConfigurationCacheInterface::set()` now records a `Severity::Warning` `Observation`
(`'Cache write failed: …'`) via the reporter instead of being silently swallowed, while still
returning the live snapshot. The composed reporter is threaded from `ConfigurationBuilder::build()`
through `ConfigurationWiringInterface::cachingResolver()` into `CachingResolver`. **R13** — removed
all `else` blocks from `FileModificationProbe::__invoke()` (ternary), `DiagnosticsReport::summary()`
(early-branch + `if`), and `FileSystemCache::set()` (ternary on `instanceof TypedSnapshot`); the
no-`else` guideline now holds for all three target files. Gates: `composer analyse` 0 errors
(PHPStan L9), `composer cs:check` 0/261, `composer test:unit` 274 tests / 630 assertions. Behavior
locked by `CachingResolverTest` (live snapshot returned + warning observation recorded when a
reporter is present, none when absent).

---

## Milestone 4 — Optional DRY & extensibility (R17, R18)

**Goal:** reduce per-processor duplication and optionally open internal Core helpers to
extension. These are **optional** (low priority, defer unless a concrete need appears).

**Files (R18):** introduce `src/Core/AbstractNodeRewritingProcessor.php`; update
`CastingProcessor`, `ValidationProcessor` (and any future node-rewriting processors) to extend
it. **Files (R17):** consider `ScalarCasterInterface` / `RedactingRendererInterface` under
`Contract/` if external customization is anticipated.

### Tasks

1. **R18 — `AbstractNodeRewritingProcessor`.** Extract the repeated shape
   ("clone graph → loop nodes → `withProvenance(...)` / `withValue(...)`") into an `abstract`
   base implementing `ConfigurationProcessorInterface` + `ProvenanceAwareProcessorInterface`,
   exposing a `protected abstract function rewrite(ConfigurationNodeInterface $node): ConfigurationNodeInterface`.
   Subclasses implement only `rewrite()`; the base handles graph cloning, idempotent
   provenance appending, and `stageName()` (via a `protected ProcessorStage $stage` or the enum).
   Migrate `CastingProcessor` (value rewrite) and `ValidationProcessor` (validated stage) first.

2. **R17 — optional interfaces for internal helpers.** Only if external customization is a
   real requirement: add `ScalarCasterInterface` (used by `CastingProcessor`) and
   `RedactingRendererInterface`, keep the current classes as default implementations, and
   depend on the interfaces at call sites. Otherwise leave as internal `final readonly` helpers.

### Verification
- `composer analyse` (the base class must satisfy both interfaces; check ShipMonk does not
  flag the now-shared method as dead).
- `composer cs:check` clean.
- `composer test:unit` green (processor behavior must be byte-for-byte identical).

### Risk
**Low**, but **Larger churn** for R18 (touches every node-rewriting processor) — schedule last.

### Status
✅ done (2026-08-26) — **R18 implemented**; **R17 intentionally deferred**.

**R18 — extracted `src/Core/AbstractNodeRewritingProcessor` (new `abstract readonly`
base implementing `ConfigurationProcessorInterface` + `ProvenanceAwareProcessorInterface`).** It
owns the immutable-graph rewrite loop (`process()`: `before($graph)` hook → `new ConfigurationGraph()`
→ `foreach nodes → put(rewrite($node))`) and the `stageName()` identity (via a `protected
ProcessorStage $stage` set by subclasses). `CastingProcessor` and `ValidationProcessor` now `extend`
it and implement only the node-level `rewrite()` (plus a `before()` override in `ValidationProcessor`
that runs schema validation and throws `ValidationException` on failure — preserving the exact
pre-loop validation/aggregation behavior). The `cast()`/`toInt()`/… scalar helpers in `CastingProcessor`
and the schema/error-assembly logic in `ValidationProcessor` are unchanged; both subclasses gained an
optional `$stage = ProcessorStage::Casting|Validation` constructor parameter (defaults keep every
existing positional call-site working). The duplicated clone/loop/put boilerplate is gone; no other
processor was migrated because `DefaultsProcessor`/`IncludeProcessor`/`VariableProcessor` are not
simple per-node rewrites. Behavior is byte-for-byte identical — the existing `CastingProcessorTest`,
`ValidationProcessorTest`, `ConfigurationPipelineStagesTest` and `ConfigurationPipelineProcessorsTest`
all still pass (suite 274 tests / 630 assertions).

**R17 — deferred (per the milestone's own condition).** The finding says to add `ScalarCasterInterface`
/ `RedactingRendererInterface` *"only if external customization is a real requirement … otherwise
leave as internal `final readonly` helpers."* No external-customization requirement exists for this
milestone, and `ScalarCaster` is in fact consumed by `ConfigurationSnapshot`/`TypedSnapshot`, not by
`CastingProcessor` (the plan's "used by CastingProcessor" note is stale). Both helpers therefore remain
internal `final readonly` classes. If explicit extension points are later wanted, R17 is a small,
mechanical follow-up (introduce the two `Contract` interfaces, have the concretes implement them, and
re-type the call sites) — tracked but not done here.

Gates: `composer analyse` 0 errors (PHPStan L9), `composer cs:check` 0/262,
`composer test:unit` 274 tests / 630 assertions.

---

## Milestone 5 — Remove the sole abstract class via composition (R19, newly identified)

> **Audit result:** a repo-wide scan for `abstract class` finds exactly **one** abstract type:
> `src/Adapter/Decryption/CommandDecrypter`. It declares **no abstract methods** — it is a pure
> behavior-sharing base, not a contract. Everything below applies only to this one class, so
> completing this milestone fully satisfies "replace all abstract classes".

### Brainstorm & reasoning

`CommandDecrypter` `implements DecrypterInterface`, holds a `CommandRunnerInterface` + a
`$command` array, and provides the complete `decrypt()` template (feed ciphertext to the
command on stdin, return stdout, translate failures into `DecryptionException`). Its two
subclasses (`AgeDecrypter`, `SopsDecrypter`) add **zero** logic — they only pass a different
`$command` to the parent constructor. Inheritance is therefore used purely for code reuse,
which `AGENTS.md` explicitly discourages ("Composition over Inheritance").

**Why replace it**
- **Single-inheritance tax.** A class may extend only one base. `CommandDecrypter` occupies that
  slot for both decrypters for no polymorphic reason — the interface already enforces `decrypt()`.
- **Testability.** The shared `decrypt()` logic is currently only exercisable *through* a subclass.
  Extracting it yields a standalone, directly unit-testable unit.
- **Nothing is lost.** Because the base declares no abstract method, turning it into a trait or a
  composed helper loses no enforced polymorphism — the interface is the real contract.

**Option A — Trait (`CommandDecryptionTrait`).** Move the properties + `decrypt()` into a trait;
each decrypter `implements DecrypterInterface` and `use`s it, assigning `$this->runner` /
`$this->command` in its own constructor.
- *Pros:* smallest diff, no extra object, preserves the current class shape.
- *Cons:* traits cannot use constructor promotion for shared state, so the subclass must assign
  properties manually; a trait imposes no interface (the class must still `implements`); trait
  `$this` state can collide with other trait/users; and `parent::__construct` idioms disappear.
  Traits are also harder to mock/reason about than a value object.

**Option B — Composition (`CommandDecryption` helper) — recommended.** Extract the runner + command
+ `decrypt()` into a small `final readonly class CommandDecryption`. Each decrypter becomes a thin
adapter that *holds* one and delegates:
```php
final readonly class CommandDecryption
{
    public function __construct(
        private CommandRunnerInterface $runner,
        private array $command,
    ) {
    }

    public function decrypt(string $ciphertext): string
    {
        try {
            $output = $this->runner->run([...$this->command], $ciphertext);
        } catch (\Throwable $e) {
            throw new DecryptionException(sprintf('Failed to decrypt secret: %s', $e->getMessage()), 0, $e);
        }

        return rtrim($output, "\r\n");
    }
}

final class AgeDecrypter implements DecrypterInterface
{
    public function __construct(CommandRunnerInterface $runner, string $identityFile)
    {
        $this->cmd = new CommandDecryption($runner, ['age', '--decrypt', '-i', $identityFile, '-']);
    }

    public function decrypt(string $ciphertext): string
    {
        return $this->cmd->decrypt($ciphertext);
    }
}
```
- *Pros:* true composition over inheritance (matches `AGENTS.md`); the shared logic is a single
  reusable, mockable unit; each decrypter stays tiny and focused; no single-inheritance penalty.
- *Cons:* one extra (trivial, immutable) object per decrypter instance — negligible.

**Recommendation: Option B (composition).** It best matches the project's stated principles and
yields the cleanest, directly-testable seam. Option A is acceptable only if zero extra objects is a
hard constraint.

**Files:** delete `src/Adapter/Decryption/CommandDecrypter.php`; add
`src/Adapter/Decryption/CommandDecryption.php`; edit `AgeDecrypter.php`, `SopsDecrypter.php`.

### Tasks
1. Create `CommandDecryption` (`final readonly`) with the `runner`, `command` props and the
   `decrypt()` body copied verbatim from `CommandDecrypter::decrypt()`.
2. Rewrite `AgeDecrypter` / `SopsDecrypter` to `implements DecrypterInterface`, compose a
   `CommandDecryption`, and delegate `decrypt()`.
3. Delete `CommandDecrypter.php`.
4. *(Trait alternative — Option A)* skip steps 1–3 and instead add `CommandDecryptionTrait`,
   `use` it in both decrypters (assigning `$this->runner` / `$this->command` in each constructor),
   and drop `extends CommandDecrypter`.

### Verification
- `composer analyse` (no inheritance remains; interface still satisfied — expect 0 errors).
- `composer cs:check` clean.
- `composer test:unit` green; **add a direct unit test for `CommandDecryption::decrypt()`** (the
  stdin-command behavior was previously only reachable via subclasses) to lock the extracted logic.

### Risk
**Low.** Behavior-preserving: identical `$command` arrays and identical `decrypt()` body.

### Status
✅ done (2026-08-26) — **Option B (composition)** implemented.

`src/Adapter/Decryption/CommandDecryption` (new `final readonly` helper) now owns the shared
behavior: the `CommandRunnerInterface $runner` + `list<string> $command` state and the
`decrypt()` body copied verbatim from `CommandDecrypter::decrypt()` (stdin feed → stdout,
`\Throwable` translated into `DecryptionException('Failed to decrypt secret: …', 0, $e)`,
trailing `\r\n` trimmed). `AgeDecrypter` / `SopsDecrypter` are rewritten as thin adapters that
`implements DecrypterInterface`, hold a `private readonly CommandDecryption $decryption`
(assembled with their identical command arrays in their constructors), and delegate `decrypt()`.
`CommandDecrypter.php` is deleted — no inheritance remains between them, and both decrypters keep
their public constructor signatures, so every existing call-site is unchanged.

The extracted logic gained its first direct unit test,
`tests/Unit/Adapter/Decryption/CommandDecryptionTest` (the stdin-command behavior was previously
only reachable via subclasses): it locks the exact command array + stdin ciphertext passed to the
runner, the trailing-newline trim (multi-line output preserved), and the runner-failure →
`DecryptionException` translation including the message format.

Note: after this milestone exactly one `abstract` type remains in `src/`,
`src/Core/AbstractNodeRewritingProcessor` — introduced deliberately by Milestone 4 (R18) *after*
this milestone's audit was written. Unlike `CommandDecrypter` it declares an abstract method
(`rewrite()`), so it is a genuine template-method contract rather than pure behavior-sharing
inheritance, and it stays.

Gates: `composer analyse` 0 errors (PHPStan L9 + dead-code), `composer cs:check` 0/263,
`composer test:unit` 276 tests / 636 assertions.

---

## Explicitly NOT adopted (from REVIEW.md)

These are recorded as deliberate decisions, not backlog:

- **R14 — Property hooks / virtual properties.** Skipped: `ProvenanceInterface` /
  `ConfigurationNodeInterface` expose methods named `resourceUri()`, `format()`, etc. A property
  hook with the same name as the interface method collides. The explicit getters are kept.
- **R15 — WeakMap / lazy memoization of derived node views.** Skipped: scoped in `REVIEW.md` to
  "only if profiling shows cost at scale". No benchmark indicates a leak or hot path, so the
  memoization is not introduced preemptively.
- **R16 — Fibers.** DO NOT ADOPT: synchronous, deterministic resolution is a deliberate feature
  (observability, diagnostics, reproducibility). No action.

---

## Cross-cutting acceptance (run after every milestone)

```bash
composer analyse    # PHPStan L9 + dead-code, must be green (0 errors)
composer cs:check   # @PSR12 + @PHP84Migration + no_superfluous_elseif, must be clean
composer test:unit  # full Codeception Unit suite, must be green (≥267 tests)
```

Per-milestone hard gates:
- **R9/R10a:** every `ProvenanceAwareProcessorInterface::stageName()` returns a
  `ProcessorStage::*->value`; `ValidationProcessor` no longer calls `get_class($schema)`.
- **R7:** `ConfigurationResolver` constructor type is `ConfigurationPipelineInterface`.
- **R8:** `ConfigurationBuilder` contains no `new ResolutionReporter()` / `new ExecutionContext()`
  (grep `src/Core/ConfigurationBuilder.php` for those two `new` expressions → empty).
- **R11:** `CastingProcessor::cast()` has exactly one docblock.
- **R12:** a throwing cache still yields the live snapshot and records a `warning` observation.
- **R13:** `grep -rn "else" src --include='*.php'` for the three target files → empty.

## Effort & sequencing summary

| Milestone | Items | Risk | Est. | Merge independently? |
|-----------|-------|------|------|----------------------|
| 1 | R9, R10a (R10b optional) | Low | S–M | Yes |
| 2 | R7, R8 | Low–Med | S–M | Yes (interface + wiring) |
| 3 | R11, R12, R13 | Low | S | Yes |
| 4 | R17, R18 | Low (large churn) | M | Yes (optional) |
| 5 | R19 (abstract class → composition) | Low | S | Yes |

All changes are additive/isolated and behavior-preserving; none block the existing feature
set. After Milestones 1–5 the codebase fully satisfies the `AGENTS.md` "exemplary" bar and the
open findings in `REVIEW.md` are closed; R19 closes the abstract-class audit via composition,
and R14–R16 remain intentionally not adopted.
