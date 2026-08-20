# DisenchantIt — User Guide

> Library + SDK for finding and replacing *magic* scalar values in PHP source.
> Turns repeated string literals into explicit, named `private const`
> declarations — and optionally into a shared, dependency-free `SemConv`
> registry that every class aliases through.

This guide documents the **current** public surface. DisenchantIt is a
library: you drive it programmatically and decide how it fits your workflow
(an IDE plugin, a refactor bot, a test-time linter, a batch script over a
source tree). It deliberately does **not** ship a CLI or a filesystem walker —
it operates on individual PHP source strings, which keeps it safe and
embeddable.

---

## 1. Installation

```bash
composer require it-pack/disenchant-it
```

Requirements (declared in `composer.json`):

- **PHP `^8.4`** — the codebase uses `readonly` classes, typed properties, and
  modern constant syntax (`public const string FOO = '...';`).
- `nikic/php-parser` `^5` — AST-based detection.
- `nette/php-generator` and `rector/rector` are declared as deps reserved for
  future adapter work; the current rewrite path does **not** use them.

PSR-4 autoload maps `ItPack\DisenchantIt\` → `src/`.

---

## 2. The golden rule (why it is safe)

DisenchantIt **never** `require`s, `include`s, `class_exists()`s, or otherwise
executes/autoloads the PHP it analyses or rewrites.

- **Detection** parses source into an AST and reads it; it never loads the
  class.
- **Rewriting** emits modified source text; it never re-evaluates the file.

This is what lets you run it against a class that may already be loaded
elsewhere in the same process, and it is the precondition for rewriting
without risking a *"Cannot redeclare class"* fatal. Verifying that a rewrite
did not break anything (`php -l`, or running the project's own test suite
against the working tree) is a **separate process** concern and stays out of
DisenchantIt's runtime.

---

## 3. Quick start

Every public capability is fronted by the `Disenchanter` orchestrator. Build
one through the composition-root factory — this is the only supported entry
point:

```php
<?php

declare(strict_types=1);

use ItPack\DisenchantIt\Factory\DisenchanterFactory;

$disenchanter = DisenchanterFactory::create()->build();
```

`DisenchanterFactory::create()` wires the scanner, candidate filter, grouper,
name generator, the auto-routing placement strategy, the line-based rewriter,
and the SemConv collector + alias rewriter. `->build()` returns a
`DisenchanterInterface` you can call repeatedly (it is a `readonly`,
stateless object).

### Single file, promote everything repeated at least once

```php
$code = <<<'PHP'
<?php

class User {
    public function role(): string { return 'admin-user'; }
    public function label(): string { return 'admin-user'; }
    public function guest(): string { return 'guest-user'; }
}
PHP;

$rewritten = $disenchanter->rewrite($code);
```

`$rewritten`:

```php
<?php

class User {
    private const string ADMIN_USER = 'admin-user';
    private const string GUEST_USER = 'guest-user';

    public function role(): string { return self::ADMIN_USER; }
    public function label(): string { return self::ADMIN_USER; }
    public function guest(): string { return self::GUEST_USER; }
}
```

By default `minOccurrences = 1`, so a literal is promoted even if it appears
only once. Use a higher threshold to promote *only* values repeated enough to
signal a shared concept:

```php
// Promote only values seen at least twice -> just ADMIN_USER.
$rewritten = $disenchanter->rewrite($code, minOccurrences: 2);
```

---

## 4. The pipeline at a glance

```
Source text (one PHP file)
   │
   ▼  detect()
Detect    ── AST scan of string literals, classify role, filter candidates,
   │         group by value, generate a constant name per value
   ▼  rewrite()
Decide     ── placement strategy decides where each constant lives
Rewrite    ── line-based insertion + literal → self::CONST replacement
   │
   ▼  rewriteWithSemConv()  (optional extra steps)
Collect    ── merge placements into one shared SemConv registry (interface)
Alias      ── rewrite each class const's value to point at SemConv::NAME
```

Each step is pure source-text-in / data-structure-out. There is no file I/O
inside DisenchantIt; your code supplies source strings and decides where the
result goes.

---

## 5. Detection details

### 5.1 Roles

The scanner walks every string literal and classifies it by its position in
the AST. Roles are internal hints, but they drive which literals survive
filtering (§5.2):

| Role | Where the literal sits | Candidate by default? |
|---|---|---|
| `constructor_arg` | `new Foo('literal')` | yes |
| `function_arg` | `f('literal')` | yes |
| `method_call_arg` / `call_arg` | `$this->x('literal')` / `Foo::x('literal')` | yes |
| `array_key_literal` | `['literal' => ...]` (as key) | yes |
| `array_value_literal` | `[..., 'literal']` (as value) | yes |
| `array_key` | `$arr['literal']` | yes |
| `comparison` | `=== 'literal'` | yes |
| `branch_value` | `match`/`case 'literal'` | yes |
| `exception_message` | `new SomeException('...')` | **no** |
| `format_string` | `sprintf` / `printf` / `vsprintf` / `trigger_error` first arg | **no** |
| `enum_backing_value` | `case PHP = 'php'` | **no** |
| `class_const_value` | `private const FOO = 'literal'` (the const's own value) | **no** |
| `unclassified` | anything else | yes (if identifier-shaped) |

### 5.2 The candidate filter

A literal becomes a *candidate* only if **all** of these hold:

1. Its role is not `exception_message`, `format_string`, `enum_backing_value`,
   or `class_const_value`.
2. Its value **looks identifier-shaped**: it matches
   `^[A-Za-z0-9_\-.\/:]+$` and contains no space. So `json`, `admin-user`,
   `APP_KEY`, `config/key` pass; `"Configuration key %s is empty."` does not.

> The `class_const_value` exclusion is what makes a rewrite **idempotent**: on
> a second pass the constant's own declaration value is no longer re-promoted
> (which would corrupt `private const FOO = 'bar'` into
> `private const FOO = self::FOO`). See §12.

### 5.3 Grouping and naming

Surviving candidates are grouped **by literal value** — repetition across
sites is the signal that a literal is "secretly an enum member." Each group is
assigned a deterministic UPPER_SNAKE_CASE constant name (slugify the value,
upper-case, replace non-alphanumerics with `_`; a leading digit is prefixed
with `V_`). The mapping is stable across runs:

| Literal | Constant name |
|---|---|
| `'admin-user'` | `ADMIN_USER` |
| `'guest-user'` | `GUEST_USER` |
| `'APP_KEY'` | `APP_KEY` |
| `'json'` | `JSON` |
| `'123abc'` | `V_123ABC` |

> Name collisions are resolved at placement time (a class that already declares
> a same-named constant is left untouched), and in the SemConv registry a
> differing-value collision is a **hard error** (§8.4).

---

## 6. Placement strategies

Given the occurrences of one value, the placement strategy decides *where* the
promoted constant is declared:

- **`SameClassConstantStrategy`** — all occurrences are in one class → one
  `private const` on that class.
- **`PerClassConstantStrategy`** — occurrences span several classes → each
  class gets its own same-named `private const` (no shared abstraction is
  invented).
- **`AutoConstantPlacementStrategy`** *(default)* — picks SameClass vs PerClass
  automatically based on whether a value spans one class or several.

The default factory uses `Auto`. Selecting a specific strategy is an advanced
composition topic (§11).

---

## 7. The three public methods

All three take a single PHP source string and never touch the filesystem.

### `detect(string $code): array<string, CandidateInterface>`

Returns the grouped, named candidates — useful for previews, dry-runs, or
building your own placement logic. Each `CandidateInterface` exposes:

- `value` (`string`) — the literal value.
- `constantName` (`string`) — the generated constant name.
- `occurrences` (`list<StringLiteralInterface>`) — every usage site, each with
  `value`, `line`, `role`, `className`, `methodName`.
- `count(): int` — number of occurrences.

```php
foreach ($disenchanter->detect($code) as $value => $candidate) {
    echo sprintf(
        "%s -> %s (%d occurrences)%s",
        $value,
        $candidate->constantName,
        $candidate->count(),
        PHP_EOL,
    );
}
```

### `rewrite(string $code, int $minOccurrences = 1): string`

Runs the full detect → decide → rewrite pipeline and returns the rewritten
source. Only candidates whose occurrence count meets `minOccurrences` are
promoted.

### `rewriteWithSemConv(string $code, int $minOccurrences = 1, ?string $existingSemConvSource = null): DisenchantResultInterface`

The same as `rewrite()` plus the SemConv collect + alias steps (§8). Returns a
**`DisenchantResultInterface`** with two readonly fields:

- `code` (`string`) — the rewritten class source, with each class constant
  aliased to the shared registry.
- `semconvSource` (`string`) — the generated shared SemConv registry source
  (merged with `$existingSemConvSource` when supplied, so re-runs
  accumulate rather than overwrite).

```php
$result = $disenchanter->rewriteWithSemConv($code);

file_put_contents('src/User.php', $result->code);
file_put_contents('src/Generated/SemConv.php', $result->semconvSource);
```

`DisenchantResult` is `final readonly`, so the two fields read directly:
`$result->code` and `$result->semconvSource`.

---

## 8. SemConv — the shared registry (collect + alias)

SemConv (modelled after OpenTelemetry's SemConv) is a **name-and-value
registry with zero behavior**: a single `interface` holding every collected
constant, referenced by classes rather than duplicated across them. It adds two
steps on top of the basic rewrite.

### 8.1 Step 1 — promote locally (already done by `rewrite`)

Each class gets its own `private const string NAME = 'value';`.

### 8.2 Step 2 — collect into one shared interface

All placements from the run are merged into one generated interface, e.g.
`src/Generated/SemConv.php`:

```php
<?php

declare(strict_types=1);

namespace Generated;

/**
 * Generated by DisenchantIt (SemConvCollector) — a shared, dependency-free
 * registry of constants collected from across the codebase, modeled after
 * OpenTelemetry SemConv ...
 */
interface SemConv
{
    public const string ADMIN_USER = 'admin-user';
    public const string GUEST_USER = 'guest-user';
}
```

Nothing from Step 1 is removed — the interface is **additive only**.

### 8.3 Step 3 — alias each class constant through to the registry

Each class's local constant is rewritten so its value *points at* the shared
registry instead of repeating the raw literal:

```php
<?php

namespace App;

use Generated\SemConv;

class User {
    private const string ADMIN_USER = SemConv::ADMIN_USER;
    private const string GUEST_USER = SemConv::GUEST_USER;

    public function role(): string { return self::ADMIN_USER; }
    public function label(): string { return self::ADMIN_USER; }
    public function guest(): string { return self::GUEST_USER; }
}
```

The key property: **method bodies are untouched.** Every `self::ADMIN_USER`
call site keeps working unchanged; only the constant's own declaration line
changes. A class still references *its own* constant, which is now backed by
the shared value.

> The `use Generated\SemConv;` import is inserted automatically (after the last
> existing `use`, or after `namespace ...;` if there are none). This requires
> the class to declare a `namespace`; see §12.2.

### 8.4 Merge semantics and collisions

- **Accumulation.** Pass a prior run's `semconvSource` as
  `$existingSemConvSource`; new constants are merged in (sorted by name) and
  previously collected ones are preserved. This is how you build one registry
  across many files (§9).
- **Collision = hard error.** If a constant name already exists in the
  registry with a *different* value, `SemConvCollector` throws a
  `RuntimeException`. Differing values under one name mean a genuine naming
  conflict to resolve manually — DisenchantIt refuses to silently pick one.
- **Alias idempotency.** Already-aliased declaration lines and already-present
  `use` imports are left untouched, so re-running is safe.

### 8.5 Configuring the registry (`SemConvConfig`)

The registry name, namespace, and output path are configuration, not
hardcoded. Defaults:

| Option | Default | Meaning |
|---|---|---|
| `interfaceName` | `'SemConv'` | The interface name and the unqualified alias (`SemConv::NAME`). |
| `namespace` | `'Generated'` | The namespace the generated interface lives in (and the `use` target). |
| `outputPath` | `'SemConv.php'` | Suggested file name (informational; you decide where to write it). |

To customize, assemble the factory manually so the collector and alias rewriter
share one configured `SemConvConfig` (see §11.2).

---

## 9. Multi-file workflow (building one shared registry)

DisenchantIt works on **one source string at a time**. To apply it across a
tree and accumulate a single SemConv registry, you orchestrate the loop
yourself and thread the `semconvSource` forward:

```php
<?php

declare(strict_types=1);

use ItPack\DisenchantIt\Factory\DisenchanterFactory;

$disenchanter = DisenchanterFactory::create()->build();

$files = [
    'src/User.php'   => file_get_contents('src/User.php'),
    'src/Order.php'  => file_get_contents('src/Order.php'),
];

$semconvSource = null;

foreach ($files as $path => $code) {
    $result = $disenchanter->rewriteWithSemConv(
        code: $code,
        minOccurrences: 2,
        existingSemConvSource: $semconvSource,
    );

    file_put_contents($path, $result->code);
    $semconvSource = $result->semconvSource; // accumulate across files
}

// Write the shared registry exactly once, at the end.
file_put_contents('src/Generated/SemConv.php', $semconvSource);
```

Because `rewrite()`/`rewriteWithSemConv()` are idempotent, re-running the same
script over already-rewritten files is a safe no-op (no new constants are
emitted, no self-rewrap occurs).

---

## 10. Verification & safety

DisenchantIt produces source text; it does not prove the result compiles or
behaves correctly. After writing files, verify in a **separate process**:

```bash
# Lint every rewritten file.
find src -name '*.php' -print0 | xargs -0 -n1 php -l

# Run the target project's own test suite against the working tree.
vendor/bin/phpunit
```

This separation is intentional: DisenchantIt never executes your code, so it
cannot and should not claim a rewrite is semantically safe.

---

## 11. Extending & swapping adapters

DisenchantIt is built on ports & adapters (`Contract` interfaces, concrete
`Adapter` implementations, an orchestrating `Core`, and immutable
`ValueObject` carriers). Every collaborator is supplied by a factory — no
inline `new` inside domain code — which makes the pieces independently mockable
and swappable.

### 11.1 Replace a single collaborator

Each port is an interface you can implement and inject. For example, to use a
custom candidate filter, build a `Disenchanter` from your own
`DisenchanterFactoryInterface` implementation (or extend the existing factory)
and pass your filter where `DetectorFactory::buildFilter()` is used. The
`detect()` / `rewrite()` contracts stay identical, so callers are unaffected.

### 11.2 Configure SemConv manually

To change the registry name/namespace, construct the collector and alias
rewriter from one shared `SemConvConfig` and compose the `Disenchanter`
yourself:

```php
<?php

declare(strict_types=1);

use ItPack\DisenchantIt\ConstantNameGenerator;
use ItPack\DisenchantIt\Disenchanter;
use ItPack\DisenchantIt\Factory\ConstantPlacementStrategyFactory;
use ItPack\DisenchantIt\Factory\DetectorFactory;
use ItPack\DisenchantIt\LineBasedRewriter;
use ItPack\DisenchantIt\SemConvAliasRewriter;
use ItPack\DisenchantIt\SemConvCollector;
use ItPack\DisenchantIt\ValueObject\SemConvConfig;

$semconv = new SemConvConfig(interfaceName: 'AppConstants', namespace: 'App\Generated');

$disenchanter = new Disenchanter(
    DetectorFactory::create()->build(),
    DetectorFactory::create()->buildFilter(),
    DetectorFactory::create()->buildGrouper(),
    new ConstantNameGenerator(),
    ConstantPlacementStrategyFactory::create()->buildAuto(),
    new LineBasedRewriter(),
    new SemConvCollector($semconv),
    new SemConvAliasRewriter($semconv),
);

$result = $disenchanter->rewriteWithSemConv($code);
// ->code               references `AppConstants::NAME`, with `use App\Generated\AppConstants;`
// ->semconvSource      declares `interface AppConstants` in namespace `App\Generated`
```

Keep the same `SemConvConfig` instance for the collector and the alias
rewriter, or the emitted `use` import and the registry namespace will not
match.

---

## 12. Current limitations & caveats

This is the **current** state of the API; treat the capabilities as stable and
the exact signatures as maturing until a `1.0.0` tag.

1. **One source string at a time.** There is no built-in file walker or CLI.
   You supply source strings and write results. (§9 shows the recommended
   loop.)
2. **Single-quoted literals are expected.** Replacement matches the literal in
   its `var_export` (single-quoted) form. A candidate written with double
   quotes (`"admin-user"`) is detected but its source occurrence is not
   rewritten, because the line replacement looks for the single-quoted
   representation. Normalize quoting in your source for best results.
3. **One occurrence per line is replaced.** Replacement operates on each
   recorded occurrence's exact line and replaces the first matching literal on
   that line. Two distinct occurrences of the same value on one line would see
   only the first replaced.
4. **SemConv aliasing requires a namespace.** The `use Generated\SemConv;`
   import is inserted only when the class declares a `namespace` (or an
   existing `use`). A class without a namespace will be rewritten to reference
   `SemConv::NAME` without a working import, which will not resolve at
   runtime. Keep the registry's `namespace` consistent with your autoloader.
5. **No enum promotion.** Backed-enum case values are explicitly excluded
   (promoting one to a separate `const` would fatal). Enum promotion is a
   future capability.
6. **Constant declarations are `private const string`.** The line-based
   rewriter inserts `private const string NAME = 'value';` immediately after
   each class's opening brace. Visibility/type are not yet configurable.
7. **Idempotency is guarded, not infinite.** Re-running is safe because a
   constant's own value is excluded from candidacy and already-declared /
   already-aliased lines are skipped. Hand-editing the generated SemConv file
   into a different shape can defeat the merge regex (§8.4) — treat that file
   as generated.

---

## 13. Glossary

| Term | Meaning |
|---|---|
| Magic value | A bare scalar literal (usually a string) repeated as a type tag, key, enum-like member, or config token. |
| Candidate | A detected literal that survives filtering and is worth promoting. |
| Placement | The decision of *where* a promoted constant lives (class + name + value + occurrences). |
| Occurrence | One specific usage site of a literal (class, method, line, role). |
| Role | The AST position of a literal (`method_call_arg`, `comparison`, `branch_value`, …). |
| SemConv | The shared, dependency-free constant registry (`interface`) every class aliases through. |
| Alias-through | A class constant whose value references `SemConv::NAME` rather than the raw literal. |
| Idempotent | Re-running on already-rewritten code is a safe no-op. |

For architecture, execution model, and the (planned) hexagonal layout, see
`docs/REFACTORING_PLAN.md`. For the source-text safety rule and invariants,
see `AGENTS.md`.
