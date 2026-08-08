# DisenchantIt

> A library and SDK to find and replace magic scalar values in PHP.
> Finds and replaces magic scalar values in PHP code with constants or enums.

---

## Purpose

DisenchantIt helps you turn *magic* scalar values — most often bare string literals
used as type tags, keys, enum-like members, or configuration tokens — into explicit,
named constants (and, eventually, backed enums). It targets the exact anti-pattern
where the same literal is repeated across methods and classes, silently, with no single
source of truth.

It is deliberately a **library/SDK**, not a black-box tool: its pieces are plain,
composable objects (ports + adapters) so they can be embedded in an IDE plugin, a
refactoring bot, a test-time linter, or a future CLI — without the library dictating
the surrounding workflow.

### The hard rule

DisenchantIt **never** `require`s, `include`s, `class_exists()`s, or otherwise
executes/autoloads the PHP files it analyses or rewrites. Everything is pure
**source-text in, data-structure out**:

- Detection parses source into an AST and reads it; it does not load the class.
- Rewriting produces modified source text; it does not re-evaluate the file.

This is what makes it safe to run against a class that may already be loaded elsewhere
in the same process, and it is the precondition for rewriting without risking a
"Cannot redeclare class" fatal. Verifying that a rewrite did not break anything
(`php -l`, or running the project's own test suite against the working tree) is a
**separate process** concern and stays out of DisenchantIt's runtime.

## How it works

The work splits into two independent halves that are composed by the caller.

### 1. Detection (find the candidates)

- An AST-based scan (via `nikic/php-parser`) walks every string literal in a file and
  records where it sits: class, method, line, and the **role** it plays
  (constructor arg, method-call arg, array key/value, comparison, branch value,
  enum backing value, …).
- A **candidate filter** drops literals that are clearly *not* magic values worth
  promoting — exception messages, `sprintf`/`printf` format strings, enum backing
  values, and anything that does not look identifier-shaped (prose with spaces or
  sentence punctuation is excluded).
- Surviving candidates are **grouped by value**. Repetition across multiple sites is
  the strongest signal that a literal is "secretly an enum member" and should become a
  shared constant.

### 2. Replacement (place + rewrite)

- A **placement strategy** decides *where* a promoted value's constant should live:
  - `SameClassConstantStrategy` — all occurrences are in one class → a single
    `private const` on that class.
  - `PerClassConstantStrategy` — occurrences span several classes → each class gets its
    own same-named `private const` (no new shared abstraction is invented).
  - `AutoConstantPlacementStrategy` — picks SameClass vs PerClass automatically based on
    whether the value spans one class or several.
- A **rewriter** applies the placements to source text:
  - `LineBasedRewriter` inserts the `private const` declarations (right after each
    class's opening brace) and replaces each literal occurrence with `self::CONST`,
    operating strictly per recorded line so untouched lines stay byte-for-byte
    identical. No AST reprinting, no third-party rewrite engine — formatting is
    preserved by convention.
- The **SemConv pattern** (OpenTelemetry-SemConv-inspired) layers on a shared,
  dependency-free `interface` that collects the same constants from across the whole
  scanned tree. Each class's local constant is then *aliased through* to the shared
  value (`private const string PATH = SemConv::PATH;`) — existing `self::PATH` call
  sites keep working unchanged; only the declaration line changes.

## Intended usage (library)

DisenchantIt is meant to be driven programmatically. A minimal composition of the
current building blocks looks like:

```php
use ItPack\DisenchantIt\Detector;
use ItPack\DisenchantIt\AutoConstantPlacementStrategy;
use ItPack\DisenchantIt\LineBasedRewriter;

$detector = new Detector();
$strategy = new AutoConstantPlacementStrategy();
$rewriter = new LineBasedRewriter();

// 1. scan + filter every file's string literals
$candidates = $detector->filterCandidates($detector->scanSource($code));

// 2. group by value, promote only values seen enough times
$placements = [];
foreach ($detector->groupByValue($candidates) as $value => $occurrences) {
    if (count($occurrences) < $minOccurrences) {
        continue;
    }
    array_push($placements, ...$strategy->decide($occurrences));
}

// 3. rewrite the source text (does NOT execute or autoload the file)
$rewritten = $rewriter->rewrite($code, $placements);
```

> The exact public surface above is **subject to change** as the codebase is refactored
> toward the hexagonal, port/adapter layout described in `docs/REFACTORING_PLAN.md`
> (detection, filtering, naming, and placement each become injectable ports; a Core
> service orchestrates them). Treat the *capabilities* as stable, not the *signatures*.

## Architecture (direction of travel)

The library is being shaped around Ports & Adapters:

- **`Contract`** — ports (interfaces) with no internal dependencies.
- **`Adapter`** — concrete implementations (e.g. the nikic-based detector, the
  line-based rewriter).
- **`Core`** — orchestration that depends only on ports.
- **`ValueObject`** — immutable carriers (detected literals, placements, config).
- Collaborators are supplied via **dependency injection using factories** (no inline
  `new`); factories are instantiated and expose a `build()` method.

This keeps every piece independently testable (mock the port) and lets the rewrite
engine be swapped (e.g. a future Rector-based adapter) without touching callers.

## Status

Early / maturing. The detection and line-based rewriting halves are functional PoC
extracts; the API, packaging, and supporting tooling are still settling. Expect breaking
changes until a stable release is tagged. See `docs/REFACTORING_PLAN.md` for the
roadmap of planned structural improvements (SOLID/DRY, hexagonal layout, PHP 8.4
language features, architecture rules via PHPArkitect).

## Requirements

- PHP 8.4 or newer (the codebase intends to leverage readonly classes, property hooks,
  lazy objects, and WeakMap).
- `nikic/php-parser` ^5 (detection).
- `nette/php-generator` and `rector/rector` are declared for future adapter work.
