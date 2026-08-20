# Findings — Defects & Broken Code

Correctness bugs that prevent the SOLID/hexagonal discussion from even mattering.
Anchored to `src/`. Severity: 🔴 must fix · 🟡 minor.

---

## 🔴 `Hash` value object is non-functional (HashIt)

`packages/HashIt/src/Hash.php`

The class declares **property hooks with `get` accessors that read themselves**, and never
assigns any backing value:

```php
public string $algorithm {           // line 11
    get { return $this->algorithm; } // ← reads the same property → infinite recursion
}
public bool $binary {               // line 16
    get { return $this->binary; }   // ← infinite recursion
}
public string $value {              // line 21
    get { return $this->value; }    // ← infinite recursion
}
public int $length {                // line 27
    get { return $this->length ?: strlen($this->value); } // reads unset $this->value
}
```

- Accessing any of `algorithm` / `binary` / `value` **recurses until stack overflow**.
- `__toString()` (line 38) returns `$this->value`, which is never set → empty/erroring string.
- The constructor (line 9) is empty; there is no way to populate the object.
- `HashInterface` (`packages/HashIt/src/HashInterface.php`) declares these as read-only getters, so the
  contract is **unfulfillable** by this implementation.

**Fix:** make `Hash` an immutable value object:
```php
final class Hash implements HashInterface {
    public function __construct(
        private string $value,
        private string $algorithm,
        private bool   $binary = false,
    ) {}

    public function __toString(): string { return $this->value; }
    public function value(): string  { return $this->value; }
    public function algorithm(): string { return $this->algorithm; }
    public function binary(): bool   { return $this->binary; }
    public function length(): int     { return strlen($this->value); }
}
```
(Note: PHP property hooks with only `get` have *no* backing store, so they must return a real
source — here the constructor-injected `private` fields.)

---

## 🔴 `HashFinalizer` constructor parameter is a no-op (HashIt)

`packages/HashIt/src/HashFinalizer.php`
```php
public function __construct(bool $binary = false){}   // line 11 — $binary never read
public function finalize(HashContext $context): string {
    return hash_final($context, false);                // line 15 — always TEXT
}
```
A caller passing `new HashFinalizer(true)` expects binary output and gets text. This is both a
**correctness bug** and an **LSP violation** (see SOLID/DRY doc). `BinaryHashFinalizer`
(`packages/HashIt/src/BinaryHashFinalizer.php`, correct, `hash_final($context, true)`) is the only
working variant.

**Fix:** drop the misleading parameter and rename to `TextHashFinalizer`; or make the single
finalizer honour the `bool $binary` it is given:
```php
final class HashFinalizer implements HashFinalizerInterface {
    public function __construct(private bool $binary = false) {}
    public function finalize(HashContext $context): string {
        return hash_final($context, $this->binary);
    }
}
```

---

## 🟡 `SPLFileInfo` mis-cased import (HashIt)

`packages/HashIt/src/FilepathFileinfo.php:7`
```php
use SPLFileInfo    ;   // should be `use SplFileInfo;`
```
Runs (PHP class names are case-insensitive) but breaks FQCN-normalising tooling and is a
copy-paste smell. Also the stray trailing whitespace after the `;`.

**Fix:** `use SplFileInfo;`.

---

## 🟠 `ExtendIt\Inspection\ClassInspector::inspect()` is a no-op (ExtendIt)

`packages/ExtendIt/src/Inspection/ClassInspector.php:16-19`
```php
public function inspect(ReflectionClass $class): void {
    $methods = $class->getMethods();   // computed then discarded
}
```
The method returns `void` and never uses `$methods`. It is a placeholder posing as an
implementation. Shipping public no-op methods misleads consumers about capability.

**Fix:** implement (return/collect the inspection result) or remove the class until the behavior
exists. If kept as a stub, mark it explicitly (e.g. `@todo` + `throw new \LogicException('not implemented')`)
so callers fail loudly rather than silently.

---

## 🟡 `FileInfo` can be constructed empty with wrong-typed fields (FileIt)

`packages/FileIt/src/ValueObject/FileInfo.php`
- 9 `public` mutable properties, **no constructor**, no validation.
- Declares e.g. `public int|false $size;` but a default `new FileInfo()` leaves it `null`
  (not `int|false`), so the typed declaration is a lie until something fills it.

**Fix:** `readonly` value object with a constructor requiring the essential fields and deriving/
defaulting the rest; validate types at construction.

---

## 🔴 `HashIt` is not installable / has no tests (HashIt)

`packages/HashIt/` contains source but **no `composer.json`** (unlike `DeriveIt`, `FileIt`,
`ExtendIt`). Consequences:
- No PSR-4 autoload → the package cannot be required or autoloaded standalone.
- No phpstan/cs-fixer config, **no tests** → the 🔴 bugs above went uncaught.
- It is unclear whether `Hash`/`HashFinalizer` were ever executed.

**Fix:** add `packages/HashIt/composer.json` (`ItPack\HashIt\` → `src/`), plus a minimal smoke test
that constructs a `Hash`, builds a context, updates, finalises, and asserts the value — this
alone would have surfaced both 🔴 defects.

---

## 🟡 Orphaned / overlapping types (HashIt)

- `packages/HashIt/src/HashValue.php` (`class HashValue { public function __construct(public string $value){} }`)
  overlaps with `Hash::$value` and is never referenced anywhere. Dead duplicate of "a hash value".
- `packages/HashIt/src/HashAlgorithmEnum.php` duplicates `packages/HashIt/src/HashAlgorithm.php` cases with no added
  value (see DRY doc).

**Fix:** delete `HashValue` once `Hash` is repaired; delete `HashAlgorithmEnum` in favour of the
single `HashAlgorithm` backed enum.
