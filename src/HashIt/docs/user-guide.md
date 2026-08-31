# HashIt User Guide

A complete, conceptual reference for [`it-pack/hash-it`](../README.md). It explains
*what* every feature is for, *how* the API is shaped, and *when* to reach for it.
Every section links to a runnable example in [`examples/`](../examples) so you can
copy verified code rather than re-deriving it.

If you prefer to learn by doing, start with the [Tutorial](./tutorial.md) instead.

---

## Contents

1. [What HashIt is](#what-hashit-is)
2. [Installation & requirements](#installation--requirements)
3. [Orientation: the hasher and the `Hash` value object](#orientation-the-hasher-and-the-hash-value-object)
4. [Core concepts](#core-concepts)
   - [Algorithm identifiers & URNs](#algorithm-identifiers--urns)
   - [The algorithm catalogue & metadata](#the-algorithm-catalogue--metadata)
   - [The composition root `NativeHashIt`](#the-composition-root-nativehashit)
   - [Inputs: the four modalities](#inputs-the-four-modalities)
5. [Hashing data (horizontal features)](#hashing-data-horizontal-features)
6. [Keyed & authenticated hashing](#keyed--authenticated-hashing)
7. [Key derivation](#key-derivation)
8. [Structured / canonical hashing](#structured--canonical-hashing)
9. [Tree (chunked) hashing](#tree-chunked-hashing)
10. [Resumable (pausable) hashing](#resumable-pausable-hashing)
11. [Manifests & verification formats](#manifests--verification-formats)
12. [Observability](#observability)
13. [PSR-7 streams](#psr-7-streams)
14. [Extending: a custom algorithm source](#extending-a-custom-algorithm-source)
15. [Architecture in one minute](#architecture-in-one-minute)
16. [Security & algorithm selection](#security--algorithm-selection)
17. [Troubleshooting & FAQ](#troubleshooting--faq)

---

## What HashIt is

HashIt hashes **strings, files, and streams** through PHP's `hash_*` extension,
behind a small **hexagonal (ports & adapters)** API:

- The **core** (`ItPack\HashIt\*`, excluding `Adapter` and `Format`) depends only on
  interfaces it owns. It never mentions `hash_init`/`hash_update`/OpenTelemetry/PSR-7.
- The **`Adapter`** namespace holds the concrete PHP binding (`NativeHashIt` and
  friends) plus optional integrations (observability, PSR-7).
- The **`Format`** namespace turns manifests into text (BSD/`sha256sum`/SFV) and back.
- A single **composition root**, `Adapter\NativeHashIt`, wires everything together.

This matters for you as a user because the *public surface is stable and small*:
one `HasherInterface` for plain hashing, plus a handful of *feature ports*
(`HmacHasherInterface`, `KeyDerivationInterface`, `Pbkdf2Interface`,
`Argon2Interface`, `TreeHashingInterface`, `ResumableHashingInterface`). You obtain
each from the composition root and the rest of your code depends only on the port.

> Why not just call `hash()`? HashIt adds: a rich `Hash` value object, algorithm
> metadata, keyed/HMAC, HKDF/PBKDF2/Argon2, deterministic structured framing,
> tree and resumable hashing, manifest verification, progress reporting, and
> pluggable observability — without leaking the `hash` extension into your domain
> code. See [Architecture in one minute](#architecture-in-one-minute).

---

## Installation & requirements

```bash
composer require it-pack/hash-it
```

- Requires **PHP `^8.4`** and the **`hash`** extension (`ext-hash`).
- Optional integrations are *suggested*, not required:
  - `psr/http-message` — to hash PSR-7 streams ([`Adapter\PsrStreamHasher`](../src/Adapter/PsrStreamHasher.php)).
  - `open-telemetry/api` — to emit OTel tracing spans ([`Adapter\Observability\OtelHashingObserver`](../src/Adapter/Observability/OtelHashingObserver.php)).
  - `cloudevents/sdk-php` — to emit CNCF CloudEvents ([`Adapter\Observability\CloudEventsHashingObserver`](../src/Adapter/Observability/CloudEventsHashingObserver.php)).

Install the optional packages only if you use those features; the rest of the
library works without them.

Runnable example: [`examples/00-quick-start.php`](../examples/00-quick-start.php).

---

## Orientation: the hasher and the `Hash` value object

Everything starts from the composition root:

```php
use ItPack\HashIt\Adapter\NativeHashIt;

$hasher = NativeHashIt::create();

$hash = $hasher->hash('hello');
$hash->value();   // "2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824"
$hash->algorithm(); // "sha256"
$hash->length();    // 64  (hex characters; 32 raw bytes)
$hash->binary();    // false
(string) $hash;     // same as value()
$hash->toUrn();     // "hash-it:sha256:2cf24d…"
```

`HasherInterface::hash()` always returns a [`HashInterface`](../src/HashInterface.php)
value object, never a bare string. That object carries its **algorithm**, **length**,
and **binary flag** alongside the digest, so you cannot accidentally compare a SHA-256
digest against a SHA-512 digest and get a meaningless "match". Use `equals()` for
constant-time comparison:

```php
$hash->equals($hasher->hash('hello')); // true
$hash->equals($hasher->hash('world')); // false
```

> Because `value()` is lowercase hex by default, it is safe to store in a
> `CHAR(64)` column and compare with `=` at the database layer. For constant-time
> comparison in PHP, prefer `equals()`.

See [`examples/horizontal/07-hash-value-object.php`](../examples/horizontal/07-hash-value-object.php).

---

## Core concepts

### Algorithm identifiers & URNs

Algorithms are accepted two ways:

- As the [`HashAlgorithm`](../src/HashAlgorithm.php) **enum** (type-safe, autocompletes):
  `HashAlgorithm::SHA256`, `HashAlgorithm::SHA3_256`, …
- As a **string identifier** accepted by every method (`hash($data, 'sha3-256')`).
  The string may be the enum *value* (`'sha256'`) or the enum *name* (`'SHA256'`).

`HashAlgorithm::default()` is `SHA256`. A `HashInterface` can be serialized to a
self-describing URN via `toUrn()`:

```
hash-it:<algorithm>:<hex-digest>
```

URNs are useful when you persist digests and must later know which algorithm produced
them.

```php
$hasher->hash('x', HashAlgorithm::SHA3_256)->toUrn();
// "hash-it:sha3-256:…"
```

### The algorithm catalogue & metadata

HashIt ships a queryable catalogue so you can choose algorithms by *property*, not
by memorized names:

- `HashAlgorithm::cases()` — every algorithm the build knows.
- `HashAlgorithm::recommendedAlgorithms()` / `brokenAlgorithms()` / `hmacSafeAlgorithms()`
  — curated subsets.
- `(new HashAlgorithm::SHA1)->info()` returns [`AlgorithmInfo`](../src/AlgorithmInfo.php)
  with `outputBits()`, `blockBits()`, and a `status()` of type
  [`AlgorithmStatus`](../src/AlgorithmStatus.php): `Recommended`, `Legacy`, `Weak`, or `Broken`.
- `(new HashAlgorithm::MD5)->isHmacSafe()` tells you whether the algorithm is safe to
  use inside HMAC (MD5/SHA-1 are *broken* for collision resistance but still HMAC-safe
  per RFC 6151 / RFC 6194).

This lets a policy pick, e.g., "the first recommended algorithm" instead of hardcoding
`'sha256'`, and lets you warn at startup if someone configures a `Broken` algorithm.

See [`examples/horizontal/08-algorithm-catalogue.php`](../examples/horizontal/08-algorithm-catalogue.php)
and [`examples/horizontal/09-algorithm-strength-metadata.php`](../examples/horizontal/09-algorithm-strength-metadata.php).

### The composition root `NativeHashIt`

[`NativeHashIt`](../src/Adapter/NativeHashIt.php) is the single place that knows how to
build the concrete hashers. It exposes one factory per feature:

| Factory | Returns | Purpose |
| --- | --- | --- |
| `NativeHashIt::create(?observer, ?source)` | `HasherInterface` | Plain hashing (strings/files/streams) |
| `::hmacHasher(?source)` | `HmacHasherInterface` | Keyed message authentication |
| `::keyedHasher(?observer, ?source)` | `KeyedHasherInterface` | HMAC-shaped keyed hashing |
| `::kdf(?observer)` | `KeyDerivationInterface` | HKDF key derivation (RFC 5869) |
| `::pbkdf2()` | `Pbkdf2Interface` | PBKDF2 password stretching |
| `::argon2()` | `Argon2Interface` | Argon2 password hashing |
| `::treeHasher()` | `TreeHashingInterface` | Tree / chunked hashing |
| `::resumable()` | `ResumableHashingInterface` | Pausable hashing |

Two *optional* cross-cutting dependencies can be injected once at `create()`:

- `HashingObserverInterface $observer` — receives progress/lifecycle events (see
  [Observability](#observability)).
- `AlgorithmSourceInterface $source` — overrides algorithm discovery (see
  [Extending](#extending-a-custom-algorithm-source)). This is how you enforce a
  corporate "only recommended algorithms" policy at the type level.

### Inputs: the four modalities

Every hashing path accepts one of four input *modalities* (the "horizontal" axis).
This is the key idea: **the same feature (e.g. HMAC) works over a string, a file, a
stream, or a composite**, so once you learn the modality you can apply it everywhere.

| Modality | How you pass it | Example |
| --- | --- | --- |
| String | `hash(string $data)` | `examples/horizontal/01-hash-strings.php` |
| File | `hashFile(FilepathInterface $path)` | `examples/horizontal/02-hash-files.php` |
| Stream | `hashStream($stream, …)` | `examples/horizontal/03-hash-streams.php` |
| Composite | `hashInput(CompositeInput $input)` | `examples/vertical/24-structured-composite-hashing.php` |

`Filepath` is a small value object; `new Filepath('/abs/path')` is the usual call.
Streams are processed in fixed chunks (`HasherInterface::DEFAULT_CHUNK_SIZE_BYTES`,
64 KiB) so hashing a 4 GiB file uses constant memory.

---

## Hashing data (horizontal features)

### Strings & binary

```php
$hasher->hash('abc')->value();                 // hex digest
$hasher->hashBinary('abc')->binary();           // true (raw bytes in value())
$hasher->hash('abc', HashAlgorithm::SHA512);    // algorithm override
```

`hashBinary()` returns the raw digest; `binary()` is `true` and `value()` returns the
bytes (not hex). Use binary digests when feeding a KDF or storing in a `BINARY` column.

See [`examples/horizontal/01-hash-strings.php`](../examples/horizontal/01-hash-strings.php).

### Files

```php
use ItPack\HashIt\Filepath;

$hash = $hasher->hashFile(new Filepath('/path/to/file.zip'));
```

See [`examples/horizontal/02-hash-files.php`](../examples/horizontal/02-hash-files.php).

### Streams (memory-safe, large inputs)

`hashStream()` accepts any seekable PHP stream resource or `StreamInterface` and hashes
it in 64 KiB chunks. Use it for uploads, pipes from `php://input`, or any source too
large to load at once.

```php
$stream = fopen('php://temp', 'rb+');
fwrite($stream, $bigData);
rewind($stream);

$hash = $hasher->hashStream($stream, HashAlgorithm::SHA256);
```

> **Gotcha:** `hashStream()` calls `fseek()` to re-read for verification helpers. On a
> genuinely non-seekable pipe (e.g. `popen`) PHP emits a warning and the stream is
> hashed from its current position only. Hash *seekable* streams (files, `php://temp`,
> `php://memory`) for reproducible results; see [Troubleshooting](#troubleshooting--faq).

See [`examples/horizontal/03-hash-streams.php`](../examples/horizontal/03-hash-streams.php).

### Hashing many files / a directory

```php
$map = $hasher->hashFiles(['/a.txt', '/b.txt']);        // [path => HashInterface]
$dir = $hasher->hashDirectory('/var/data');             // recursive, [path => HashInterface]
```

Both accept an optional `?HashProgressInterface $progress` for live feedback.

See [`examples/horizontal/04-hash-files-and-directory.php`](../examples/horizontal/04-hash-files-and-directory.php).

### Verifying & constant-time comparison

```php
$expected = $hasher->hash('abc');
$hasher->verify('abc', $expected);          // true (constant-time)
$hasher->verify('abcd', $expected);         // false
```

`verify()` is the equivalent of `hash(...)->equals($expected)` but reads more clearly
at a call site. There is also `verifyBinary()` for raw-digest comparison.

See [`examples/horizontal/05-verify.php`](../examples/horizontal/05-verify.php).

### Progress reporting

Pass a `HashProgressInterface` (one method: `onBytes(int $processed, ?int $total)`) to
`hashStream`, `hashFiles`, or `hashDirectory`. It is invoked as bytes flow; `null`
total means "unknown length" (e.g. a live stream).

```php
$progress = new class implements \ItPack\HashIt\HashProgressInterface {
    public function onBytes(int $processed, ?int $total): void {
        printf("%.1f%%\n", $total === null ? NAN : 100 * $processed / $total);
    }
};
$hasher->hashStream($stream, HashAlgorithm::SHA256, 64 * 1024, $progress);
```

See [`examples/horizontal/06-progress.php`](../examples/horizontal/06-progress.php).

---

## Keyed & authenticated hashing

To authenticate (not just fingerprint) data, use **HMAC** via `KeyedHasherInterface`
(obtained with `keyedHasher()`). A slimmer `HmacHasherInterface` (via `hmacHasher()`)
exposes only `hmac()` if you don't need verification/bulk helpers.

```php
$hmac = NativeHashIt::create()->keyedHasher();

$tag = $hmac->hmac('message', $key);            // HashInterface, hex
$hmac->verify('message', $key, $tag);           // true/false (constant-time)
$hmac->hmacFile(new Filepath('/f'), $key);      // file variant
$hmac->hmacStream($stream, $key);               // stream variant
```

HMAC proves the message came from someone holding `$key` *and* was not tampered with.
Never use a raw (unkeyed) hash as a MAC. HMAC works over strings, files, and streams;
pick an HMAC-safe algorithm — `SHA-256` is the default and is always safe
(`isHmacSafe()` is `true`).

See [`examples/vertical/20-hmac.php`](../examples/vertical/20-hmac.php).

---

## Key derivation

Three distinct ports solve three distinct problems — do not confuse them:

| Port | Function | Use for |
| --- | --- | --- |
| `KeyDerivationInterface` (HKDF) | Expand a *high-entropy* secret into many keys | Deriving sub-keys from a shared secret / KDF from a DH result |
| `Pbkdf2Interface` | Stretch a *low-entropy* password (iterations) | Compatibility with PBKDF2-based systems |
| `Argon2Interface` | Hash & verify a password (memory-hard) | Storing user passwords |

### HKDF (RFC 5869)

```php
$kdf = NativeHashIt::create()->kdf();

$okm = $kdf->derive(
    ikm: $sharedSecret,   // input keying material
    salt: $salt,          // optional; null => RFC 5869 zero-salt default
    length: 32,           // output length in bytes
    info: 'encryption',   // context label: same ikm+info => same key
    algorithm: HashAlgorithm::SHA256,
);
```

`derive()` is pure over `KeyedHasherInterface` (HMAC). `salt` may be omitted: a
*missing* salt uses the RFC default (HashLen zero octets), while an explicit empty
string `''` is a deliberate empty salt — the two are distinct, as RFC 5869 intends.

See [`examples/vertical/21-key-derivation-hkdf.php`](../examples/vertical/21-key-derivation-hkdf.php).

### PBKDF2 (password stretching)

```php
$pbkdf2 = NativeHashIt::create()->pbkdf2();

$derived = $pbkdf2->derive(
    password: $password,
    salt: $salt,
    iterations: 600_000,     // tune to your hardware
    length: 32,
    algorithm: HashAlgorithm::SHA256,
);
```

PBKDF2 is iteration-hardened, intended for stretching *weak, human-chosen* secrets.
Prefer **Argon2** for new password storage; keep PBKDF2 for interop.

See [`examples/vertical/22-key-derivation-pbkdf2.php`](../examples/vertical/22-key-derivation-pbkdf2.php).

### Argon2 (password hashing)

```php
$argon2 = NativeHashIt::create()->argon2();

$stored = $argon2->hash($password);   // opaque, self-contained (algo+params+salt)
$argon2->verify($password, $stored);  // true/false
```

`Argon2Interface::hash()` uses `PASSWORD_ARGON2ID` and returns a self-describing string
you can store directly (it already embeds the salt and cost parameters). **Never** store
the raw password or a plain (unkeyed) hash of it.

See [`examples/vertical/23-password-argon2.php`](../examples/vertical/23-password-argon2.php).

---

## Structured / canonical hashing

When you need one digest for *several* fields (a record, a request, a config blob) and
must get the **same digest for the same logical content regardless of how it was
chunked**, use `CompositeInput`:

```php
use ItPack\HashIt\HashInput\CompositeInput;
use ItPack\HashIt\HashInput\StringInput;

$record = new CompositeInput([
    new StringInput('user:42'),
    new StringInput('role:admin'),
]);

$d = $hasher->hashInput($record, HashAlgorithm::SHA256);
```

`CompositeInput` frames each child with an 8-byte little-endian length prefix
(`HashInputInterface::FRAME_LENGTH_BYTES`), so `"ab"+"c"` and `"a"+"bc"` hash
**differently** (unlike naive concatenation, which would collide). The same composite
always yields the same digest — this is your deterministic "canonical form" for
structured data. Mix `StringInput`, `FileInput`, and `StreamInput` freely.

See [`examples/vertical/24-structured-composite-hashing.php`](../examples/vertical/24-structured-composite-hashing.php).

---

## Tree (chunked) hashing

For large inputs you want a digest that is (a) distinct from the plain `hash()` of the
same bytes, yet (b) computable chunk-by-chunk (parallelizable, resumable per chunk).
That is `TreeHashingInterface`:

```php
$tree = NativeHashIt::create()->treeHasher();

$root = $tree->treeHash(
    new StringInput($data),
    chunkSize: 64 * 1024,         // MUST be recorded alongside the digest
    algorithm: HashAlgorithm::SHA256,
);
```

Properties (see [`docs/ADR-0001-tree-hash-combiner.md`](./ADR-0001-tree-hash-combiner.md)):

- `treeHash()` is a **strict generalization** of `hash()`: if `chunkSize >= len(input)`,
  the result equals `hash()` (single chunk, no combination).
- For `chunkSize < len(input)` the root digest is **distinct** from `hash()` — they can
  never be confused.
- The digest depends on `(bytes, chunkSize, algorithm)`. A different `chunkSize` yields a
  different tree hash, so **record `chunkSize` with the digest** (e.g. in its URN/metadata).

See [`examples/vertical/25-tree-hashing.php`](../examples/vertical/25-tree-hashing.php).

---

## Resumable (pausable) hashing

Hash a stream, snapshot it, and resume later — even in another process:

```php
$rh = NativeHashIt::create()->resumable();

$handle = $rh->start(HashAlgorithm::SHA256);
$handle->update($chunk1);
$checkpoint = $handle->checkpoint();        // persist this string anywhere
$handle->update($chunk2);
$full = $handle->finalize();

// later / elsewhere:
$resumed = $rh->resume($checkpoint);
$resumed->update($chunk2);
$again = $resumed->finalize();
$full->equals($again);                      // true
```

`checkpoint()` returns a portable string; the live handle keeps working afterward.
`resume()` rebuilds the exact in-progress state (algorithm + binary flag are encoded in
the checkpoint).

> **Plain algorithms only.** Keyed/HMAC contexts cannot be serialized by the native `hash`
> extension, so `checkpoint()` throws for them. See
> [`docs/ADR-0002-resumable-hashing.md`](./ADR-0002-resumable-hashing.md).

See [`examples/vertical/26-resumable-hashing.php`](../examples/vertical/26-resumable-hashing.php).

---

## Manifests & verification formats

To record *expected* digests for many files and later verify a tree on disk, build a
[`HashManifest`](../src/HashManifest.php) and verify it with
[`ManifestVerifier`](../src/ManifestVerifier.php):

```php
use ItPack\HashIt\HashManifest;
use ItPack\HashIt\ManifestVerifier;
use ItPack\HashIt\Format\BsdFormat;

$manifest = new HashManifest(HashAlgorithm::SHA256);
foreach ($files as $path) {
    $manifest = $manifest->with($path, $hasher->hashFile(new Filepath($path)));
}

$text = (new BsdFormat())->serialize($manifest);   // BSD `*.sha256`-ish format
// … store $text …

$report = (new ManifestVerifier())->verify(
    (new BsdFormat())->parse($text),
    $hasher,
);
$report->allMatched();    // bool
$report->matched();       // [path => HashInterface]
$report->mismatched();    // [HashMismatch, …]
$report->missing();       // [path, …]
$report->throwOnFailure(); // throws if anything is not matched
```

Three text formats are shipped in the `Format` namespace:
[`BsdFormat`](../src/Format/BsdFormat.php) (BSD `hash` style),
[`GnuSha256Format`](../src/Format/GnuSha256Format.php) (`sha256sum` style), and
[`SfvFormat`](../src/Format/SfvFormat.php) (SFV `.sfv`). Serialization and parsing are
kept *out* of the core (roadmap guardrail #7) — the `Format` namespace is the walled
garden for I/O.

See [`examples/vertical/27-manifests-and-formats.php`](../examples/vertical/27-manifests-and-formats.php).

---

## Observability

Inject a [`HashingObserverInterface`](../src/HashingObserverInterface.php) at
`NativeHashIt::create($observer)` to receive lifecycle progress events
(`HashObservation` over `HashOperation`/`HashProgress`). Two adapters ship behind the
optional deps:

- **OpenTelemetry** ([`OtelHashingObserver`](../src/Adapter/Observability/OtelHashingObserver.php),
  needs `open-telemetry/api`) — emits a span per hashing operation.
- **CloudEvents** ([`CloudEventsHashingObserver`](../src/Adapter/Observability/CloudEventsHashingObserver.php),
  needs `cloudevents/sdk-php`) — emits a CNCF CloudEvent per operation.

Both are guarded by `class_exists` in their examples, so the rest of the library works
without the optional packages.

```php
$hasher = NativeHashIt::create(new OtelHashingObserver(/* tracer */));
$hasher->hash('anything'); // emits a span
```

See [`examples/vertical/28-observability.php`](../examples/vertical/28-observability.php).

---

## PSR-7 streams

If you already have a PSR-7 `StreamInterface` (e.g. from Nyholm), hash it without
converting to a PHP resource:

```php
use ItPack\HashIt\Adapter\PsrStreamHasher;

$hash = (new PsrStreamHasher())->hash($psr7Stream, HashAlgorithm::SHA256);
```

Needs `psr/http-message` (+ a concrete implementation such as `nyholm/psr7`).

See [`examples/vertical/29-psr7-streams.php`](../examples/vertical/29-psr7-streams.php).

---

## Extending: a custom algorithm source

Implement [`AlgorithmSourceInterface`](../src/AlgorithmSourceInterface.php) to enforce a
policy at the type level — e.g. "only recommended algorithms" — and inject it into any
factory via the `$source` argument:

```php
final class RecommendedOnlySource implements \ItPack\HashIt\AlgorithmSourceInterface
{
    public function algorithms(): array { return HashAlgorithm::recommendedAlgorithms(); }
    public function supports(string $id): bool { /* … */ }
    public function resolve(string $id): HashAlgorithm { /* … */ }
}

$hasher = NativeHashIt::create(source: new RecommendedOnlySource());
$hasher->hash('x', 'md5'); // throws: md5 not allowed by policy
```

A [`CompositeAlgorithmSource`](../src/AlgorithmSource/CompositeAlgorithmSource.php)
lets you layer your source on top of [`NativeAlgorithmSource`](../src/AlgorithmSource/NativeAlgorithmSource.php).

See [`examples/vertical/30-custom-algorithm-source.php`](../examples/vertical/30-custom-algorithm-source.php).

---

## Architecture in one minute

```
your code ──► HasherInterface / HmacHasherInterface / …   (ports, in Core)
                 │
                 ▼
           NativeHashIt  (composition root: Adapter namespace)
                 │
                 ├──► NativeContextFactory / NativeHashContext  (wraps ext-hash)
                 ├──► Adapter\Observability\*   (optional, behind deps)
                 └──► Format\* / HashInput\*     (Core + walled Format)
```

- Your code depends only on **ports** (`*Interface`). Swapping the PHP `hash` binding
  for, say, a hardware HSM is an `Adapter` change, not a call-site change.
- The **core never references `Format`** (guardrail #7): serialization/parsing lives in
  the walled `Format` namespace; verification logic lives in core.
- [`deptrac.yaml`](../deptrac.yaml) enforces the layering in CI, so the architecture
  cannot silently erode.

See [`examples/vertical/31-composition-root.php`](../examples/vertical/31-composition-root.php)
for a tour of building each port from the root.

---

## Security & algorithm selection

- **Integrity / fingerprints:** use a `Recommended` algorithm — `SHA-256`/`SHA-512` or
  a SHA-3 variant (e.g. `SHA3_256`/`SHA3_512`). Avoid `MD5`/`SHA-1` for integrity: they are `Broken` for
  collision resistance.
- **Authentication (proving origin + tamper-resistance):** use **HMAC** (§ keyed
  hashing), never a bare hash. MD5/SHA-1 are collision-broken but still *HMAC-safe*;
  prefer SHA-256 anyway.
- **Deriving keys from a secret:** use **HKDF** for high-entropy material, **PBKDF2**
  for interop with iteration-stretched passwords.
- **Storing user passwords:** use **Argon2id** (`Argon2Interface`). Store the opaque
  returned string; never store the password or a plain hash of it.
- **Keep keys out of source control.** Load HMAC keys / KDF salts / Argon2 from a secret
  manager, not a literal in code.
- **Tree hashes:** persist `chunkSize` with the digest, or verification will mismatch.
- **Resumable hashes:** only plain algorithms checkpoint; design keyed flows to complete
  in one process.

The catalogue helpers (`recommendedAlgorithms()`, `isHmacSafe()`, `info()->status()`)
let you *code* these rules instead of documenting them.

---

## Troubleshooting & FAQ

**`hashStream()` prints a warning on my pipe.**
You passed a non-seekable stream (e.g. `popen`). The library calls `fseek()` to enable
re-reads; on a non-seekable stream PHP warns and the hash proceeds from the current
position. Use a seekable stream (`fopen` of a file, `php://temp`, `php://memory`) for
reproducible digests. (This is a library limitation of `hashStream`, not of `hash()`.)

**My tree hash doesn't match later.**
You changed `chunkSize`, or you compared a tree hash against a plain `hash()`. They are
deliberately different. Record `chunkSize` with the digest.

**`checkpoint()` throws "cannot serialize HMAC context".**
Resumable hashing supports plain algorithms only. HMAC/keyed contexts are not
serializable by the native `hash` extension.

**`resolve()` throws `InvalidArgumentException`.**
The identifier isn't known to the active `AlgorithmSourceInterface` — likely a custom
policy source (e.g. recommended-only) rejecting it. See [Extending](#extending-a-custom-algorithm-source).

**Where are the runnable examples?**
[`examples/README.md`](../examples/README.md) indexes all 22 examples by feature, with a
table of what each demonstrates.
