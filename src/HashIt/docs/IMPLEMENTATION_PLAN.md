# HashIt — Implementation Plan

> Tactical, file-level plan. Read after [`ROADMAP.md`](./ROADMAP.md).
> All paths relative to `src/HashIt/`. Defect references use the repo-level
> `docs/FINDINGS_*.md` IDs.

## Execution order

```
Phase 0  composer.json + QA config + docs/        (foundation, no behavior change)
Phase 1  Hash (rewrite) · HashFinalizer (fix) · del HashValue
Phase 2  HashAlgorithm (authoritative) · del HashAlgorithmEnum · del HMAC_SAFE dup
Phase 3  HashContextInterface (NEW) · NativeHashContext (NEW) · repoint ports · del updater/finalizer or delegate
Phase 4  Hasher (NEW) · hashFile use-case · composition root
Phase 5  tests + examples
Phase 6  README
```

Phases are ordered so each builds on a green baseline; **do not start Phase 1 until
Phase 0's `composer install` + `phpstan` baseline is green** (so the 🔴 defects are caught
by tests the moment they're introduced/fixed).

---

## Phase 0 — Foundation

### 0.1 `composer.json` (NEW)
Mirror sibling packages; declare the namespace and QA dev-deps.
```json
{
    "name": "it-pack/hash-it",
    "autoload": { "psr-4": { "ItPack\\HashIt\\": "src/" } },
    "autoload-dev": { "psr-4": { "ItPack\\HashIt\\Tests\\": "tests/" } },
    "require": { "php": ">=8.1" },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "phpstan/phpstan": "^1.11",
        "friendsofphp/php-cs-fixer": "^3.0"
    },
    "scripts": {
        "test": "vendor/bin/phpunit",
        "analyse": "vendor/bin/phpstan analyse",
        "cs:check": "vendor/bin/php-cs-fixer fix --dry-run --diff",
        "cs:fix": "vendor/bin/php-cs-fixer fix"
    }
}
```

### 0.2 QA config (NEW)
- `phpstan.neon` — `level: 8`, `paths: [src]`.
- `.php-cs-fixer.php` — PSR-12 + `declare(strict_types=1)` + `ordered_class_elements`,
  imported from the pattern used by `DeriveIt/.php-cs-fixer.php`.

### 0.3 Directory layout going forward
```
src/HashIt/
  src/                      <- CORE (must never reference \HashContext)
    Hash.php
    HashInterface.php
    HashAlgorithm.php
    HmacSafe.php
    HashContextInterface.php     (NEW port)
    ContextFactoryInterface.php
    FilepathInterface.php
    Hasher.php                  (NEW core service)
  src/Adapter/               <- EDGE (the only place \HashContext appears)
    NativeHashContext.php       (NEW)
    NativeContextFactory.php    (NEW, was ContextFactory.php)
    NativeFilepathFileinfo.php  (was FilepathFileinfo.php)
  tests/
  examples/
  docs/
```

---

## Phase 1 — Repair core value objects

### 1.1 `Hash.php` (REWRITE) — fixes DEFECT 🔴 infinite-recursion
Property hooks with only `get` have **no backing store**; they must return a real source.
Make it an immutable value object:

```php
<?php
declare(strict_types=1);

namespace ItPack\HashIt;

use ItPack\HashIt\src\HashInterface;final class Hash implements HashInterface
{
    public function __construct(
        private string $value,
        private string $algorithm,
        private bool   $binary = false,
    ) {}

    public function __toString(): string   { return $this->value; }
    public function value(): string         { return $this->value; }
    public function algorithm(): string     { return $this->algorithm; }
    public function binary(): bool          { return $this->binary; }
    public function length(): int           { return strlen($this->value); }
}
```
> Note: this changes the *access* style from property hooks to methods. If the property-hook
> API must stay (callers use `$hash->value`), keep hooks but back them with the constructor
> fields: `public string $value { get => $this->value; }` is still recursive — so back with a
> **separate private field**, e.g. `private string $v; public string $value { get => $this->v; }`.
> Recommend the method form for clarity.

### 1.2 `HashFinalizer.php` (FIX) — fixes DEFECT 🔴 no-op `$binary`
Either honour the flag, or drop it. Recommended (honour it, keep the name):

```php
<?php
declare(strict_types=1);
namespace ItPack\HashIt;

use ItPack\HashIt\Adapter\NativeHashContext;use ItPack\HashIt\src\HashFinalizerInterface; // or the owned context port

final class HashFinalizer implements HashFinalizerInterface
{
    public function __construct(private bool $binary = false) {}
    public function finalize(NativeHashContext $context): string
    {
        return $context->finalize($this->binary);
    }
}
```
(`BinaryHashFinalizer.php` becomes redundant after this — delete it, or keep as
`new HashFinalizer(true)`.)

### 1.3 Delete `HashValue.php`
Superseded by `Hash`. No references exist in the package.

---

## Phase 2 — Single source of truth for algorithms (DRY)

### 2.1 `HashAlgorithm.php` — keep as the **only** definition
Already a backed enum with `#[HmacSafe]` attributes + `isHmacSafe()`, `default()`.
Add a derived helper so the constant array is never hand-maintained:
```php
public static function hmacSafeAlgorithms(): array
{
    $safe = [];
    foreach (self::cases() as $case) {
        foreach ((new \ReflectionEnumUnitCase(self::class, $case->name))
                     ->getAttributes(HmacSafe::class) as $attr) {
            $safe[] = $case;
            break;
        }
    }
    return $safe;
}
```
(Or cache via `static` like today — but source is now the enum, not a duplicated array.)

### 2.2 Delete `HashAlgorithmEnum.php`
Duplicate of `HashAlgorithm` with no added behavior.

### 2.3 Delete the `HMAC_SAFE` constant array in `HashAlgorithms.php`
`hmacSafeAlgorithms()` currently returns `HashAlgorithms::HMAC_SAFE` (line 216) — repoint it
to the attribute-derived list (2.1). Keep `HashAlgorithms` only if other code references its
constants; otherwise delete the file too.

---

## Phase 3 — Hexagonal ports (stop leaking `\HashContext`)

### 3.1 `HashContextInterface.php` (NEW port, in `src/`)
The core never sees PHP's `\HashContext`. Owned abstraction:
```php
<?php
declare(strict_types=1);
namespace ItPack\HashIt;

interface HashContextInterface
{
    public function update(string $data): void;
    public function finalize(bool $binary): string;
}
```

### 3.2 `Adapter/NativeHashContext.php` (NEW, the only `\HashContext` user)
```php
<?php
declare(strict_types=1);
namespace ItPack\HashIt\Adapter;

use HashContext;
use ItPack\HashIt\HashContextInterface;

final class NativeHashContext implements HashContextInterface
{
    private HashContext $ctx;
    public function __construct(string $algorithm)
    {
        $this->ctx = hash_init($algorithm);
    }
    public function update(string $data): void { hash_update($this->ctx, $data); }
    public function finalize(bool $binary): string { return hash_final($this->ctx, $binary); }
}
```

### 3.3 Repoint `ContextFactoryInterface.php`
```php
interface ContextFactoryInterface
{
    public function build(string $algorithm): HashContextInterface;
}
```
Move the old `ContextFactory.php` body into `Adapter/NativeContextFactory.php` returning
`new NativeHashContext($this->algo)`.

### 3.4 Fold or delegate `HashUpdater` / `HashFinalizer`
**Recommended (DRY + fewer leak points):** delete `HashUpdaterInterface`/`HashUpdater`
and `HashFinalizerInterface`/`HashFinalizer`/`BinaryHashFinalizer`. Their behavior now lives in
`HashContextInterface::update()`/`finalize()`. `Hasher` (Phase 4) calls the context directly.

**Alternative (minimal churn):** keep them as thin delegates:
```php
final class NativeHashUpdater implements HashUpdaterInterface {
    public function update(HashContextInterface $c, string $data): void { $c->update($data); }
}
```
Pick **one**; the recommended fold is cleaner and removes the original leak entirely.

### 3.5 `FilepathInterface.php` / `Adapter/NativeFilepathFileinfo.php`
- Fix the `use SplFileInfo;` casing (DEFECT 🟡).
- Keep interface as-is (`__toString(): string`); the adapter is the only `\SplFileInfo` user.

---

## Phase 4 — Composition root & use-cases

### 4.1 `Hasher.php` (NEW core service — depends only on ports)

```php
<?php
declare(strict_types=1);
namespace ItPack\HashIt;

use ItPack\HashIt\src\ContextFactoryInterface;use ItPack\HashIt\src\Hash;use ItPack\HashIt\src\HashAlgorithm;use ItPack\HashIt\src\HashInterface;final class Hasher
{
    public function __construct(
        private ContextFactoryInterface $factory,
        private ?HashAlgorithm $defaultAlgo = null,
    ) {}

    public function hash(string $data, string|HashAlgorithm $algorithm = 'sha256'): HashInterface
    {
        $algo   = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;
        $ctx    = $this->factory->build($algo);
        $ctx->update($data);
        return new Hash($ctx->finalize(false), $algo, false);
    }

    public function hashBinary(string $data, string|HashAlgorithm $algorithm = 'sha256'): HashInterface
    {
        $algo = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;
        $ctx  = $this->factory->build($algo);
        $ctx->update($data);
        return new Hash($ctx->finalize(true), $algo, true);
    }
}
```

### 4.2 File use-case (consumes the orphaned `FilepathInterface`)
```php
public function hashFile(FilepathInterface $path, string|HashAlgorithm $algorithm = 'sha256'): HashInterface
{
    return $this->hash((string) file_get_contents((string) $path), $algorithm);
}
```
(Wrap `file_get_contents` in a port if streaming matters later — out of scope for now.)

### 4.3 Composition root (NEW `src/HashIt/Container.php` or a `Hasher::configure()` factory)
The single place native adapters are bound:
```php
public static function default(): Hasher
{
    return new Hasher(new NativeContextFactory(/* algo resolved per-call */));
}
```
> Note: `NativeContextFactory` needs the algorithm at `build()` time (PHP's `hash_init`
> requires it). Either pass `algo` to `build(string $algorithm)` (preferred — see 3.3) or
> construct the factory per-algorithm. The plan uses `build(string $algorithm)`.

---

## Phase 5 — Tests & examples

### 5.1 `tests/HashTest.php`
- construct with value/algo/binary; assert `__toString()` == value, `length()` == `strlen(value)`,
  `binary()` flag.

### 5.2 `tests/HashAlgorithmTest.php`
- `hmacSafeAlgorithms()` returns the expected ~50 cases; `isHmacSafe()` true for `sha256`,
  false for `crc32`; `default()` == `sha256`.

### 5.3 `tests/NativeHashContextTest.php` (parity)
- `new NativeHashContext('sha256')` → `update('abc')` → `finalize(false)` equals
  `hash('sha256', 'abc')`; `finalize(true)` equals `hash('sha256', 'abc', true)`.

### 5.4 `tests/HasherTest.php`
- `Hasher` with `NativeContextFactory` → `hash('abc')` equals `hash('sha256','abc')`;
  `hashBinary(...)` equals binary form; `hashFile()` over a temp file matches.

### 5.5 `examples/hash.php`
Minimal runnable smoke script printing a few digests.

---

## Phase 6 — Docs
- `src/HashIt/README.md`: purpose, install, usage sketch (`Hasher`, `HashAlgorithm`),
  "why ports / why no `\HashContext` in core".
- Add link from repo `docs/CODE_REVIEW.md` status table (HashIt: 🔴 → 🟢).

---

## Risk notes
- **PHP version:** `HashContext` as a class exists in PHP 8.1+; the package already requires
  `>=8.1`. Property hooks need PHP 8.4 — but Phase 1 switches `Hash` to **methods**, so the
  package can stay 8.1-compatible. Confirm before keeping any hook syntax.
- **`hash_hmac` / HMAC:** `HmacSafe` attribute already gates it; a later phase can add an
  `HmacHasher` without touching the core design.
- **Back-compat:** `HashAlgorithmEnum` / `HashValue` are deleted — verify nothing external
  references them (grep the monorepo; only `HashIt` itself does).
