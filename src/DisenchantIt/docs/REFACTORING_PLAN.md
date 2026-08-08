# DisenchantIt — Refactoring Plan

> Companion to `README.md`. This document records concrete refactoring candidates
> found during a detailed review of the Phase-0 extraction of the `var/lab` PoC into
> `src/` (PSR-4 autoloadable artifacts), assessed against:
>
> - **SOLID** (SRP / OCP / LSP / ISP / DIP)
> - **DRY**
> - **Ports & Adapters** (hexagonal architecture)
> - **Dependency Injection via Factories** (no inline `new`; factories are
>   instantiated, expose `build()`, and MAY proxy their constructor through a
>   static `create()`)
> - **PHP 8.4+ language features**: property hooks, lazy objects, WeakMap /
>   WeakReference, Fibers, readonly classes, backed enums
> - **Traits** for composition where they reduce duplication
>
> Each finding lists: location, principle, severity, current behaviour, proposed
> refactoring. Severity follows the project convention.
>
> Severity: 🔴 strong candidate (real cohesion / coupling / duplication issue) ·
> 🟠 worthwhile (clarity / future-proofing / testability) · 🟡 minor / hygiene.
>
> **Scope note:** this plan is the *polish* pass that precedes the test suite. No
> test is written against the current shape on purpose — the refactors below are
> the seams the tests will lock in.

---

## 0. Canonical Factory contract (applies to every finding below)

The user-specified factory rules, written once so every later finding can rely on
them:

1. A factory is **instantiated first**; it never creates product instances through a
   `static` method. No `static function build()` / `static function make()`.
2. The product is created via an instance method named **`build()`**.
3. A factory MAY expose a `static function create(...): self` that **proxies its own
   constructor** — this lets the composition root build a factory *without* the `new`
   language construct. `create()` returns the factory, never the product.
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

## 1. DIP — `Detector` constructs its entire toolchain inline

- **Location:** `src/Detector.php:30` (`__construct` default `new RoleClassifier()`),
  `src/Detector.php:38-53` (`scanSource` builds `new ParserFactory()` at `:40`,
  `new NodeTraverser()` at `:48`, `new \PhpParser\NodeVisitor\ParentConnectingVisitor()` at `:51`,
  `new MagicStringVisitor($this->classifier)` at `:53`)
- **Principle:** DIP, SRP, testability
- **Severity:** 🔴

`Detector` is simultaneously the *detection port adapter* and the *composition root*
for the nikic parser stack. Every collaborator is hard-wired with `new`:
- the default `RoleClassifier` cannot be replaced by a mock in a unit test;
- the parser/traverser/visitor are rebuilt on every `scanSource()` call (wasteful);
- the visitor is **stateful** (accumulates `$found`) yet is constructed inline inside
  the scan, coupling the one-shot lifecycle to the detector.

- **Proposed refactoring:**
  - Introduce the port **`Contract\StringLiteralDetectorInterface`** (`scanSource(): list<StringLiteral>`,
    `filterCandidates(): list<StringLiteral>`, `groupByValue(): array`) and keep `Detector`
    as the adapter (`Adapter\NikicStringLiteralDetector`).
  - Inject collaborators via constructor (no defaults):
    - `RoleClassifierInterface` (see §6),
    - a `ParserFactoryInterface` / `TraverserFactoryInterface` (or a single
      `AstScannerFactoryInterface`) that the composition root wires once,
    - a `MagicStringVisitorFactoryInterface` whose `build(RoleClassifierInterface): MagicStringVisitor`
      is called **per scan** (the visitor is one-shot; the factory owns that lifecycle).
  - `Detector` becomes `final readonly` and holds no `new`.

---

## 2. SRP — `Detector` is four responsibilities in one class

- **Location:** `src/Detector.php` — `scanSource()` (parse + traverse),
  `filterCandidates()` (policy), `groupByValue()` (grouping),
  `suggestConstantName()` (naming) are all static-ish methods on the detector.
- **Principle:** SRP, DRY, ISP
- **Severity:** 🔴

Detection, candidate-eligibility policy, grouping, and constant-name generation are
four distinct concerns. `suggestConstantName()` is a `static` method (forbidden pattern
per §0) invoked by two strategy classes — a hidden coupling that also blocks mocking.

- **Proposed refactoring:**
  - Extract **`Contract\CandidateFilterInterface`** (`filterCandidates(): list<StringLiteral>`)
    — owns the "is this a magic-value candidate" decision (currently the role exclusions
    `exception_message` / `format_string` / `enum_backing_value` plus
    `RoleClassifier::looksIdentifierLike()`).
  - Extract **`Contract\CandidateGrouperInterface`** (`groupByValue()`).
  - Extract **`Contract\ConstantNameGeneratorInterface`** with
    `generate(string $value, ?string $role): string` — the `suggestConstantName` logic,
    now injectable and mockable. Build it via a `ConstantNameGeneratorFactory`.
  - `Detector` keeps only `scanSource()` (+ orchestration of the three collaborators).

---

## 3. DIP — `AutoConstantPlacementStrategy` and the strategies build siblings with `new`

- **Location:** `src/AutoConstantPlacementStrategy.php:16-17`
  (`= new SameClassConstantStrategy()` / `= new PerClassConstantStrategy()` defaults),
  `src/SameClassConstantStrategy.php:28` (`new ConstantPlacement(...)`),
  `src/PerClassConstantStrategy.php:25` (`new ConstantPlacement(...)`)
- **Principle:** DIP, DRY
- **Severity:** 🔴

Three separate `new` sites, two of which (`SameClass` / `PerClass`) duplicate the exact
"build a `ConstantPlacement` from occurrences" expression. The default-`new` on the
auto-strategy also prevents injecting a mock and re-implements strategy selection inline.

- **Proposed refactoring:**
  - Inject `ConstantPlacementFactoryInterface` (§0) into both strategies; replace every
    `new ConstantPlacement(...)` with `$this->placements->build(...)`.
  - Inject the two sub-strategies into `AutoConstantPlacementStrategy` (no default `new`);
    construct them at the composition root via `…Factory::create()->build()`.
  - The `count($classNames) <= 1` dispatch in `AutoConstantPlacementStrategy::decide()`
    is acceptable, but to satisfy OCP for *future* strategies, inject an
    `iterable<ConstantPlacementStrategyInterface>` registry and pick by a declared
    capability (e.g. `supports(int $distinctClassCount): bool`) instead of the hard-coded
    branch.

---

## 4. DIP — `SemConvCollector` / `SemConvAliasRewriter` default `new SemConvConfig()`

- **Location:** `src/SemConvCollector.php:18` (`private SemConvConfig $config = new SemConvConfig()`),
  `src/SemConvAliasRewriter.php:32` (same default)
- **Principle:** DIP, testability
- **Severity:** 🔴

`SemConvConfig` is a value object that both collectors reach into (interface name,
namespace, FQCN). Defaulting it with `new` inside two classes means the configuration
cannot be supplied/mocked from outside, and the "how to build a config" knowledge is
duplicated in two constructors.

- **Proposed refactoring:**
  - Introduce **`Contract\SemConvConfigFactoryInterface`** with
    `build(?string $interfaceName = null, ?string $namespace = null, ?string $outputPath = null): SemConvConfig`.
  - The composition root calls `SemConvConfigFactory::create()->build(...)` and injects
    the resulting `SemConvConfig` into both collectors (no default `new`).
  - Consider promoting `SemConvConfig` to a richer **value object** in
    `ValueObject/` (see §7) rather than a config carrier.

---

## 5. Ports & Adapters — the orchestration lives in a CLI script, not in Core

- **Location:** `var/lab/disenchant.php:62-170` (scan → filter → group → decide
  placements → rewrite), duplicated intent in `var/lab/classes.php`.
- **Principle:** Ports & Adapters (hexagonal), SRP, OCP
- **Severity:** 🔴

The entire domain flow (threshold filtering, placement routing, per-file rewriting) is
embedded in a procedural CLI script that `require`s the lab files. There is no Core
service, so the logic cannot be unit-tested, reused by an IDE plugin, or swapped for a
dry-run adapter without copying the script.

- **Proposed refactoring:**
  - Extract a **Core** service, e.g. `Core\Disenchanter` (or `DisenchantService`),
    depending only on ports:
    `StringLiteralDetectorInterface`, `CandidateFilterInterface`,
    `CandidateGrouperInterface`, `ConstantPlacementStrategyInterface`,
    `RewriterInterface`, and a **`Contract\PromotionPolicyInterface`** (`meetsThreshold(int $occurrences): bool`)
    that replaces the script's `--min-occurrences` literal.
  - `var/lab/disenchant.php` (and a future `bin/disenchant`) becomes a **thin CLI
    adapter**: parse argv → build factories → assemble the `Disenchanter` → print report.
    All domain rules move into Core.
  - Introduce **`Contract\PhpFileFinderInterface`** + `Adapter\RecursivePhpFileFinder`
    (see §10) so file discovery is also a port, not a copy-pasted `getAllPhpFiles()`.

---

## 6. ISP / LSP — `RoleClassifier` and `MagicStringVisitor` are concrete, not ports

- **Location:** `src/RoleClassifier.php:17`, `src/MagicStringVisitor.php:19`
- **Principle:** ISP, LSP, DIP
- **Severity:** 🟠

`RoleClassifier` (role classification) and `MagicStringVisitor` (AST collection) are
concrete classes with no interface. The detector depends on the concrete
`RoleClassifier`, so classification cannot be swapped (e.g. a regex fallback, or a
tokenizer that doesn't need nikic) and cannot be mocked.

- **Proposed refactoring:**
  - Port **`Contract\RoleClassifierInterface`** (`classify(): string`,
    `looksIdentifierLike(): bool`); `RoleClassifier` → `Adapter\AstRoleClassifier`.
  - Port **`Contract\MagicStringVisitorInterface`** extending `NodeVisitor` semantics;
    the concrete visitor moves to `Adapter\`. The visitor stays **mutable by design**
    (it accumulates `$found` during a traversal) — document that explicitly and keep it
    single-use (built per scan via its factory, §1).

---

## 7. PHP 8.4 — readonly classes, property hooks, value objects

- **Location:** `src/StringLiteral.php`, `src/ConstantPlacement.php` (`final class`,
  *not* `readonly`), `src/RoleClassifier.php`, `src/Detector.php`,
  `src/LineBasedRewriter.php`, `src/SameClassConstantStrategy.php`,
  `src/PerClassConstantStrategy.php`, `src/AutoConstantPlacementStrategy.php`,
  `src/SemConvCollector.php`, `src/SemConvAliasRewriter.php`, `src/SemConvConfig.php`
- **Principle:** immutability, PHP 8.4 features, DRY
- **Severity:** 🟠

Several classes are already stateless-but-not-`readonly`. `StringLiteral` and
`ConstantPlacement` are pure DTOs whose properties are `readonly` yet the *class* is not
marked `readonly` — a missed guarantee. `SemConvConfig::$outputPath` is a property
assigned in the constructor from `interfaceName`/namespace — a computed value that
belongs behind a **property hook**, not a stored field.

- **Proposed refactoring:**
  - Mark `StringLiteral`, `ConstantPlacement`, `RoleClassifier`, `Detector`,
    `LineBasedRewriter`, the three strategies, `SemConvCollector`, and
    `SemConvAliasRewriter` as **`final readonly class`** (where they hold no mutable
    state). `MagicStringVisitor` is intentionally excluded (accumulator, §6). This is
    also what PHPArkitect's "classes should be final" rule (§16) will enforce once
    added, so the refactor and the guardrail are one and the same.
  - **Property hooks (PHP 8.4)** on `SemConvConfig`:
    - `public string $outputPath { get => $this->outputPathInternal ?? $this->interfaceName . self::DOT . self::PHP_FILE_EXTENSION; }`
      (virtual/computed property; the constructor only stores the optional override).
    - Optionally expose `fullyQualifiedName()` as a `get` hook `fullyQualifiedName`
      (virtual property) so callers read `$config->fullyQualifiedName`.
  - Promote `SemConvConfig` to `ValueObject/SemConvConfig` and give it value-object
    equality (`equals()`); promote `ConstantPlacement` / `StringLiteral` semantics into
    the `ValueObject/` namespace for consistency with sibling packages.
  - **Backed enum `LiteralRole`** (string-backed) replaces the bare `string $role`
    magic strings in `StringLiteral` and the role comparisons in `Detector::filterCandidates`
    and `RoleClassifier::classify()`. Removes a whole class of typo-prone string literals
    (DRY + type safety). Likewise an enum/`Constants` collection for `RoleClassifier::NOISE_FUNCTIONS`.

---

## 8. PHP 8.4 — Lazy Objects for the parser stack

- **Location:** `src/Detector.php:55-72` (`ParserFactory` / `NodeTraverser` /
  `ParentConnectingVisitor` constructed inside `scanSource`)
- **Principle:** performance, Lazy Objects (PHP 8.4)
- **Severity:** 🟠

The nikic parser and traverser are non-trivial objects rebuilt on every scan. For a
large scanned tree (many files) this is repeated allocation of essentially static
tooling.

- **Proposed refactoring:**
  - Build the parser/traverser **once** at the composition root and inject them
    (DIP, §1). Additionally, wrap the heavy `Parser` in a
    **`LazyObjectInterface` / `ReflectionClass::newLazyGhost()`** so it is only fully
    initialised on first `parse()` — useful when a `Detector` is constructed but a given
    run ends early (dry-run, no candidates). The visitor remains one-shot (§1/§6).

---

## 9. PHP 8.4 — WeakMap for parse-result and collected-entry caching

- **Location:** `src/Detector.php:38-53` (re-parses the same source on repeated scans,
  e.g. multi-pass / test runs), `src/SemConvCollector.php:39-83` (`collect()` re-parses
  an existing SemConv file via `extractExistingEntries()` at `:83` each run)
- **Principle:** WeakMap / WeakReference, performance
- **Severity:** 🟡

Repeated scans of the same file (or re-collection against an unchanged SemConv file)
re-do expensive work. A raw string cannot be a `WeakMap` key, so today there is no
cheap identity-keyed cache.

- **Proposed refactoring:**
  - Introduce a **`ValueObject\SourceCode`** immutable wrapper around a file's contents
    (also clarifies method signatures: `scanSource(SourceCode $code)`).
  - Cache the parsed AST behind a `WeakMap<SourceCode, array>` inside the detector's
    injected scanner factory, so GC reclaims the cache when the source object dies —
    no memory leak, no manual invalidation.
  - `SemConvCollector` caches `extractExistingEntries()` results in a
    `WeakMap<SourceCode, array<string,string>>` keyed by the existing-source object.

---

## 10. DRY — file discovery is copy-pasted across three lab scripts

- **Location:** `var/lab/disenchant.php:23-36`, `var/lab/classes.php:8-17`,
  `var/lab/find-strings.php:10-30` (identical `getAllPhpFiles()`)
- **Principle:** DRY, Ports & Adapters
- **Severity:** 🟠

The recursive PHP-file walker is triplicated. It also reaches into the filesystem
directly, which is an adapter concern leaking into scripts.

- **Proposed refactoring:**
  - **`Contract\PhpFileFinderInterface`** (`find(string $path): iterable<SplFileInfo>`)
    + **`Adapter\RecursivePhpFileFinder`** (the single implementation).
  - Optional **`Trait\PhpFileScanningTrait`** only if a consumer needs the helper without
    implementing the port (prefer the port).

---

## 11. DRY — constant-declaration rendering is duplicated

- **Location:** `src/LineBasedRewriter.php:119`
  (`sprintf('    private const string %s = %s;', $name, var_export($value, true))`) and
  `src/SemConvCollector.php:137`
  (`sprintf('    public const string %s = %s;', $name, var_export($value, true))`)
- **Principle:** DRY
- **Severity:** 🟠

Both produce a `const NAME = <encoded value>;` line from a name + value. Different
visibility is the only delta; the encoding (`var_export` + indentation) is identical.

- **Proposed refactoring:**
  - A small **`ValueObject\ConstantDeclaration`** (immutable: `name`, `value`,
    `visibility`) with a `toCodeLine(int $indentLevel = 4): string` method that centralises
    the `var_export` encoding and indentation. Both `LineBasedRewriter` and
    `SemConvCollector` (and any future generator) call it.
  - Companion **`Trait\ConstantRenderingTrait`** if multiple generators want the helper
    without injecting the value object (prefer the value object).

---

## 12. DRY — `use`-statement insertion logic is a reusable concern

- **Location:** `src/SemConvAliasRewriter.php:82-106` (`ensureUseImport()` + the
  `NAMESPACE_REGEX` / `USE_STATEMENT_REGEX` anchors at `:26-27`)
- **Principle:** DRY, composition (traits)
- **Severity:** 🟡

Inserting a `use` statement after the last existing `use` / `namespace` is generic
source-text editing that any future generator (e.g. a `SharedConstantStrategy` adapter)
will need again.

- **Proposed refactoring:**
  - Extract **`Trait\NamespaceUseInserterTrait`** (`insertUseStatement(string $code, string $fqcn): string`)
    reusable by `SemConvAliasRewriter` and future adapters. Keep the regex anchors
    (`NAMESPACE_REGEX`, `USE_STATEMENT_REGEX`) inside the trait.
  - Optionally pair with a **`Trait\LineEditorTrait`** providing `explodeLines()` /
    `implodeLines()` / `insertAfterLine()` shared by `LineBasedRewriter` and
    `SemConvAliasRewriter` (both already do line-array surgery).

---

## 13. Error handling — generic `RuntimeException` instead of domain exceptions

- **Location:** `src/SemConvCollector.php:127-138` (`throw new RuntimeException(...)` on
  SemConv name collisions)
- **Principle:** maintainability, LSP, consistency with sibling packages
- **Severity:** 🟠

A generic `RuntimeException` is thrown for a domain-specific collision. Sibling packages
use a marker interface (`RunItExceptionInterface extends Throwable`) so callers can
catch the package's exceptions by a single type and so the package never leaks vendor /
SPL types as its public contract.

- **Proposed refactoring:**
  - Add **`Contract\DisenchantItExceptionInterface extends Throwable`** (marker) plus
    concrete `SemConvCollisionException`, `ParseFailureException`,
    `DuplicateConstantException`, all implementing it.
  - `SemConvCollector` throws `SemConvCollisionException`; the CLI adapter catches
    `DisenchantItExceptionInterface` and renders a clean message.

---

## 14. OCP — strategy selection hard-codes a branch

- **Location:** `src/AutoConstantPlacementStrategy.php:125-135`
  (`$classCount <= 1 ? $this->sameClass->decide(...) : $this->perClass->decide(...)`)
- **Principle:** OCP, ISP
- **Severity:** 🟡

Adding a third strategy (the PoC itself names a future `SharedConstantStrategy`) means
editing this method.

- **Proposed refactoring:**
  - Give `ConstantPlacementStrategyInterface` a `supports(int $distinctClassCount): bool`
    (or a declared `priority`) and let `AutoConstantPlacementStrategy` (renamed
    `CompositeConstantPlacementStrategy`) iterate an injected
    `iterable<ConstantPlacementStrategyInterface>` registry, picking the first that
    `supports()`. New strategies = new adapter + registration, no edit to the composer.

---

## 15. Hygiene — first-class callable syntax & precise return types

- **Location:** `src/Detector.php` (`fn (StringLiteral $lit): bool => ...` inside
  `array_filter` / `array_map`), `src/AutoConstantPlacementStrategy.php`
- **Principle:** hygiene, PHP 8.4 idioms
- **Severity:** 🟡

Callbacks can be expressed as first-class callables (`StringLiteral::value(...)`,
`RoleClassifier::looksIdentifierLike(...)`) for brevity and static analysis clarity.
Several methods already carry PHPDoc `@return` but no native return type on the port
methods (e.g. `ConstantPlacementStrategyInterface::decide(): array` can be native).

- **Proposed refactoring:**
  - Use first-class callable syntax where a method/function reference exists.
  - Add native return types to all port methods (`RewriterInterface`,
    `ConstantPlacementStrategyInterface`, new ports) for stronger PHPStan inference.

---

## 16. Architecture guardrails — introduce PHPArkitect + ADRs

- **Location:** cross-cutting (new `src/Contract`, `src/Adapter`, `src/Core`,
  `src/ValueObject`, `src/Domain` layout)
- **Principle:** Ports & Adapters sustainability
- **Severity:** 🟠

The current `src/` is flat (single namespace `ItPack\DisenchantIt\`). Introducing
`Contract/` / `Adapter/` / `Core/` / `ValueObject/` sub-namespaces is allowed by the
existing PSR-4 prefix (`"ItPack\\DisenchantIt\\": "src/"`) — no composer change needed —
but without a rule, an adapter will eventually `use` a Core class or a vendor type will
leak into Core.

We have moved away from **Deptrac** in favour of **PHPArkitect**
(<https://github.com/phparkitect/arkitect>): it expresses the same layered
architecture checks *and* lets us write project-specific rules as plain PHP
`Expression` classes, which Deptrac's YAML cannot. A working reference config lives at
`var/lab/phparkitect-from-deptrac.php` (ported from a Deptrac `deptrac.yaml`); it shows
three rule shapes we will carry over and extend.

- **Proposed refactoring:**
  - Add a package-root `phparkitect.php` (Arkitect `Config`) that:
    - builds a `ClassSet::fromDir(__DIR__ . '/src')`, and
    - declares the layers with `Architecture::withComponents()`
      (`->component('Contract')->definedBy('ItPack\DisenchantIt\Contract\*')`, and
      likewise for `Core`, `Adapter`, `ValueObject`), then encodes the dependency
      allowance via `->where('Contract')->mayDependOnComponents()` (nothing internal),
      `->where('Core')->mayDependOnComponents('Contract','ValueObject')`,
      `->where('Adapter')->mayDependOnComponents('Contract')` (never `Core`),
      `->where('ValueObject')->mayDependOnComponents()` (nothing) — `->rules()` to
      register them. This is the exact layer discipline from `ConfigureIt` ADR-0002,
      just expressed in Arkitect instead of Deptrac.
  - Port the three custom `Expression` rules from the example config, which directly
    *validate the outcomes of this plan*:
    - **"interfaces should only depend on interfaces"** — keeps `Contract` pure
      (only PHP/std + other interfaces), backing §1/§6.
    - **"classes should be final"** (composition over inheritance) — enforces the
      `final readonly class` move in §7 and the factory rule in §0.
    - **"final classes must be backed by interfaces"** (mockability) — enforces that
      *every* concrete class (adapters, strategies, factories, collectors) implements a
      port, which is the heart of the hexagonal split in §1/§5/§6.
  - Add project-specific `Expression` rules as needed (Arkitect's advantage over
    Deptrac), e.g. a guard that a `Factory` type exposes a `build()` instance method and
    has no `static` product-creation method (hardening the §0 contract), and that
    `Core` never references a vendor FQCN such as `PhpParser\*`.
  - Record the decisions as ADRs: **ADR-0001 Ports & Adapters layout**,
    **ADR-0002 Factories & DI (the §0 contract)**, **ADR-0003 PHP 8.4 feature usage**,
    **ADR-0004 Architecture rules enforced via PHPArkitect**.

---

## Refactoring sequence (recommended order)

| # | Finding | ADR | Effort | Pays off for |
|---|---------|-----|--------|--------------|
| 1 | §0 Factory contract + §3 strategy/`ConstantPlacement` DI | ADR-0002 | S | §2, §5, all factories |
| 2 | §1 `Detector` → port + inject parser/visitor factories | ADR-0001 | M | testability, §8 |
| 3 | §2 split `CandidateFilter` / `CandidateGrouper` / `ConstantNameGenerator` | ADR-0001 | M | SRP, testability |
| 4 | §5 `Disenchanter` Core + `PromotionPolicy` + `PhpFileFinder` | ADR-0001 | M | §10, hexagonal CLI |
| 5 | §4 `SemConvConfig` factory + `SemConvConfigFactory` | ADR-0002 | S | DIP |
| 6 | §6 `RoleClassifier` / `MagicStringVisitor` ports | ADR-0001 | M | swap/mock |
| 7 | §7 readonly + property hooks + `LiteralRole` enum + ValueObjects | ADR-0003 | M | type safety, 8.4 |
| 8 | §11 `ConstantDeclaration` VO + §12 traits | — | S | DRY |
| 9 | §13 domain exception hierarchy | — | S | clean contract |
| 10 | §9 Lazy Objects + §8/§9 WeakMap caching | ADR-0003 | M | performance |
| 11 | §14 OCP registry for strategies | ADR-0001 | S | future strategies |
| 12 | §16 PHPArkitect + ADRs | ADR-0004 | M | guardrails |
| 13 | §15 callable syntax / native return types | — | S | hygiene |

---

## Verification gate for refactors

- **PSR-4 / autoload:** `composer dump-autoload` clean; every artifact resolves by
  class name (one class per file, filename == class name).
- **PHPStan:** level 9, 0 errors (existing gate from sibling packages).
- **php-cs-fixer:** 0 files needing fixing.
- **PHPArkitect:** `vendor/bin/phparkitect check` → 0 violations, covering the §16
  layered components (`Contract`/`Core`/`Adapter`/`ValueObject` dependency allowances)
  and the three custom `Expression` rules (interfaces depend only on interfaces; classes
  are `final`; final classes are backed by interfaces). Optionally the §0 `Factory`
  `build()` / no-static-product guard.
- **Unit tests (added after this polish):** each new port gets a mock-based unit test;
  adapters get fixture-based tests (`Fixture/*` source strings); the `WeakMap`/`LazyObject`
  behaviour is covered with explicit life-cycle assertions. The test suite must stay
  green across every step above — each step is independently shippable.
- **No adapter imports a Core class; no Core class imports a vendor type** (ADR-0001 /
  ADR-0002 preserved).
