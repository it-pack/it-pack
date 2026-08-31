# HashIt Tutorial

A hands-on, progressive tour of [`it-pack/hash-it`](../README.md). Work through the
lessons in order; each one states a *goal*, shows the *minimum code*, and links to the
**runnable example** that demonstrates it end-to-end.

> Every snippet below is a trimmed version of a file in [`examples/`](../examples).
> Clone the repo and run `php examples/<file>.php` to see the real output.

Prerequisites: PHP `^8.4` with the `hash` extension, and
`composer require it-pack/hash-it`.

---

## Lesson 1 — Install and compute your first hash

**Goal:** hash a string and print its hex digest, algorithm, and URN.

```php
use ItPack\HashIt\Adapter\NativeHashIt;

$hasher = NativeHashIt::create();
$hash   = $hasher->hash('hello');

echo $hash->value();      // 2cf24dba5fb0a30e…  (lowercase hex)
echo $hash->algorithm();  // "sha256"
echo $hash->toUrn();      // "hash-it:sha256:2cf24d…"
```

`hash()` returns a `Hash` **value object**, not a string — it carries its algorithm and
length so you can never confuse digests of different algorithms.

→ Full example: [`examples/00-quick-start.php`](../examples/00-quick-start.php)

---

## Lesson 2 — Hash strings and files

**Goal:** fingerprint both an in-memory string and a file on disk.

```php
use ItPack\HashIt\Filepath;

$hasher->hash('abc')->value();                 // string -> hex
$hasher->hashFile(new Filepath('/tmp/x'))->value(); // file  -> hex
```

Files are read in 64 KiB chunks, so size does not affect memory use.

→ [`examples/horizontal/01-hash-strings.php`](../examples/horizontal/01-hash-strings.php)
→ [`examples/horizontal/02-hash-files.php`](../examples/horizontal/02-hash-files.php)

---

## Lesson 3 — Hash streams without loading them into memory

**Goal:** hash a large or streaming source (upload, `php://temp`) in constant memory.

```php
$stream = fopen('php://temp', 'rb+');
fwrite($stream, $bigData);
rewind($stream);

$hash = $hasher->hashStream($stream, HashAlgorithm::SHA256);
```

Use **seekable** streams (files, `php://temp`, `php://memory`) so the digest is
reproducible. Non-seekable pipes warn and hash from the current position only
(see the [User Guide FAQ](../docs/user-guide.md#troubleshooting--faq)).

→ [`examples/horizontal/03-hash-streams.php`](../examples/horizontal/03-hash-streams.php)

---

## Lesson 4 — Verify integrity (constant-time)

**Goal:** check that data still matches a previously stored digest.

```php
$expected = $hasher->hash('abc');

$hasher->verify('abc',  $expected); // true
$hasher->verify('abcd', $expected); // false
```

`verify()` is constant-time (`Hash::equals` under the hood). Prefer it over string `==`.

→ [`examples/horizontal/05-verify.php`](../examples/horizontal/05-verify.php)

---

## Lesson 5 — Report progress on big jobs

**Goal:** show a progress bar while hashing a large stream or many files.

```php
use ItPack\HashIt\Contract\HashProgressInterface;

$progress = new class implements HashProgressInterface {
    public function onBytes(int $processed, ?int $total): void {
        printf("%.1f%%\n", $total === null ? NAN : 100 * $processed / $total);
    }
};

$hasher->hashStream($stream, HashAlgorithm::SHA256, 64 * 1024, $progress);
$hasher->hashFiles(['/a','/b'], HashAlgorithm::SHA256, $progress);
```

→ [`examples/horizontal/06-progress.php`](../examples/horizontal/06-progress.php)

---

## Lesson 6 — Authenticate data with HMAC

**Goal:** prove a message is from a key holder *and* untampered — not just fingerprint it.

```php
use ItPack\HashIt\Adapter\NativeHashIt;

$hmac = NativeHashIt::create()->keyedHasher();

$tag = $hmac->hmac('message', $key);
$hmac->verify('message', $key, $tag); // true
```

HMAC is **authentication**; a bare hash is only a fingerprint. Use HMAC whenever the
digest must be secret-key protected.

→ [`examples/vertical/20-hmac.php`](../examples/vertical/20-hmac.php)

---

## Lesson 7 — Derive and stretch keys

**Goal:** turn secrets into keys for encryption / storage. Pick the right tool:

- **HKDF** — expand a *high-entropy* shared secret into sub-keys.
  → [`examples/vertical/21-key-derivation-hkdf.php`](../examples/vertical/21-key-derivation-hkdf.php)
- **PBKDF2** — stretch a *low-entropy* password (interop).
  → [`examples/vertical/22-key-derivation-pbkdf2.php`](../examples/vertical/22-key-derivation-pbkdf2.php)
- **Argon2id** — hash & verify user **passwords** (store the returned string).
  → [`examples/vertical/23-password-argon2.php`](../examples/vertical/23-password-argon2.php)

```php
$kdf  = NativeHashIt::create()->kdf();
$key  = $kdf->derive(ikm: $secret, salt: $salt, length: 32, info: 'enc');

$argon2 = NativeHashIt::create()->argon2();
$stored = $argon2->hash($password);      // store this; verify later
$argon2->verify($password, $stored);
```

See [Security & algorithm selection](../docs/user-guide.md#security--algorithm-selection)
for *when* to use each.

---

## Lesson 8 — Hash structured records deterministically

**Goal:** one stable digest for several fields, independent of how they were chunked.

```php
use ItPack\HashIt\HashInput\CompositeInput;
use ItPack\HashIt\HashInput\StringInput;

$record = new CompositeInput([
    new StringInput('user:42'),
    new StringInput('role:admin'),
]);
$digest = $hasher->hashInput($record, HashAlgorithm::SHA256);
```

`CompositeInput` length-prefixes each child, so `"ab"+"c"` ≠ `"a"+"bc"` — no concatenation
collisions. Same record always yields the same digest.

→ [`examples/vertical/24-structured-composite-hashing.php`](../examples/vertical/24-structured-composite-hashing.php)

---

## Lesson 9 — Hash huge files with tree hashing

**Goal:** a single digest for a large file, computed chunk-by-chunk and distinct from
`hash()`.

```php
use ItPack\HashIt\HashInput\StringInput;

$tree  = NativeHashIt::create()->treeHasher();
$root  = $tree->treeHash(new StringInput($data), chunkSize: 64 * 1024);
```

If `chunkSize >= len(input)` the result equals `hash()`; otherwise it is distinct.
**Record `chunkSize` with the digest** — a different size means a different tree hash.

→ [`examples/vertical/25-tree-hashing.php`](../examples/vertical/25-tree-hashing.php)
→ design note: [`docs/ADR-0001-tree-hash-combiner.md`](./ADR-0001-tree-hash-combiner.md)

---

## Lesson 10 — Pause and resume a hash

**Goal:** snapshot an in-progress stream hash and finish it later / in another process.

```php
$rh      = NativeHashIt::create()->resumable();
$handle  = $rh->start(HashAlgorithm::SHA256);
$handle->update($chunk1);
$checkpoint = $handle->checkpoint();     // persist this string
$handle->update($chunk2);
$full = $handle->finalize();

$again = $rh->resume($checkpoint)->update($chunk2)->finalize();
$full->equals($again);                   // true
```

Plain algorithms only — HMAC contexts are not serializable.

→ [`examples/vertical/26-resumable-hashing.php`](../examples/vertical/26-resumable-hashing.php)
→ design note: [`docs/ADR-0002-resumable-hashing.md`](./ADR-0002-resumable-hashing.md)

---

## Lesson 11 — Ship and check a manifest

**Goal:** record expected digests for many files, then verify a tree on disk.

```php
use ItPack\HashIt\HashManifest;
use ItPack\HashIt\ManifestVerifier;
use ItPack\HashIt\Format\BsdFormat;

$manifest = new HashManifest(HashAlgorithm::SHA256);
foreach ($files as $path) {
    $manifest = $manifest->with($path, $hasher->hashFile(new Filepath($path)));
}
$text = (new BsdFormat())->serialize($manifest);   // store this

$report = (new ManifestVerifier())->verify((new BsdFormat())->parse($text), $hasher);
$report->allMatched();   // false if any file changed or went missing
```

`VerificationReport` separates `matched()` / `mismatched()` / `missing()`; call
`throwOnFailure()` to fail closed.

→ [`examples/vertical/27-manifests-and-formats.php`](../examples/vertical/27-manifests-and-formats.php)

---

## Lesson 12 — Observe and integrate

**Goal A — trace hashes** with OpenTelemetry or emit CloudEvents (optional deps):

```php
$hasher = NativeHashIt::create(new OtelHashingObserver(/* tracer */));
$hasher->hash('anything'); // emits a span
```

→ [`examples/vertical/28-observability.php`](../examples/vertical/28-observability.php)

**Goal B — hash a PSR-7 stream** without converting to a PHP resource (needs
`psr/http-message`):

```php
use ItPack\HashIt\Adapter\PsrStreamHasher;

$hash = (new PsrStreamHasher())->hash($psr7Stream, HashAlgorithm::SHA256);
```

→ [`examples/vertical/29-psr7-streams.php`](../examples/vertical/29-psr7-streams.php)

---

## Lesson 13 — Compose it yourself (advanced)

**Goal:** build every port from the single composition root and enforce an algorithm
policy via a custom source.

```php
use ItPack\HashIt\Adapter\NativeHashIt;

$hasher = NativeHashIt::create(source: new RecommendedOnlySource());
// $hasher->hash('x', 'md5'); // throws: md5 rejected by policy
```

The composition root (`NativeHashIt`) is the only class that wires the concrete
adapters; your code depends only on ports. Implement
[`AlgorithmSourceInterface`](../src/AlgorithmSourceInterface.php) to make policy
enforcement a *type-level* guarantee.

→ [`examples/vertical/30-custom-algorithm-source.php`](../examples/vertical/30-custom-algorithm-source.php)
→ [`examples/vertical/31-composition-root.php`](../examples/vertical/31-composition-root.php)
→ architecture overview: [`docs/user-guide.md#architecture-in-one-minute`](./user-guide.md#architecture-in-one-minute)

---

## Where to go next

- **Concepts & API reference:** [User Guide](./user-guide.md)
- **Full example index:** [examples/README.md](../examples/README.md)
- **Algorithm catalogue & security rules:** [User Guide § Security](../docs/user-guide.md#security--algorithm-selection)
