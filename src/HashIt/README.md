# hash-it

> Part of the **It-Pack** family.

Hashes strings and files via PHP's `hash_*` extension, behind a small **hexagonal (ports & adapters)** API. The core depends only on interfaces it owns; the concrete PHP binding lives in the `Adapter` namespace and is wired in a single composition root.

## Install

```bash
composer require it-pack/hash-it
```

Requires PHP `^8.4` and the `hash` extension (`ext-hash`).

## Documentation

- **[User Guide](docs/user-guide.md)** — concepts, full API reference, and *when to use which feature* (HMAC, HKDF/PBKDF2/Argon2, tree, resumable, manifests, observability, PSR-7, custom sources, security).
- **[Tutorial](docs/tutorial.md)** — a progressive, hands-on walkthrough (13 lessons) that links to a runnable example at each step.
- **[Examples](examples/README.md)** — index of all 22 runnable examples, mapped to features.
- **Architecture decisions** — [ADR-0001 tree hash combiner](docs/ADR-0001-tree-hash-combiner.md), [ADR-0002 resumable hashing](docs/ADR-0002-resumable-hashing.md).

## Usage

```php
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Adapter\NativeFilepathFileinfo;
use ItPack\HashIt\HashAlgorithm;

$hasher = NativeHashIt::create();

// text digest (default sha256)
$hash = $hasher->hash('abc');
$hash->value();      // ba7816bf8f01cfea… (== hash('sha256', 'abc'))
$hash->algorithm();  // 'sha256'
$hash->binary();     // false
$hash->length();     // 64

// binary digest — 32 raw bytes
$binary = $hasher->hashBinary('abc');

// choose an algorithm: a string id or a HashAlgorithm case
$hasher->hash('abc', HashAlgorithm::SHA512);
$hasher->hash('abc', 'md5');

// hash a file via any FilepathInterface adapter
$hash = $hasher->hashFile(
    new NativeFilepathFileinfo(new \SplFileInfo('/path/to/file')),
);
```

`Hasher` always returns an immutable `Hash` value object (`HashInterface`).

Streaming and bulk operations (`hashStream`, `hashFiles`, `hashDirectory` and the
keyed `hmacStream`/`hmacFiles`) accept an optional `?HashProgressInterface $progress`
(last argument, default `null`) that reports byte-level progress as hashing proceeds:

```php
use ItPack\HashIt\HashProgress;

$progress = HashProgress::fromCallable(
    static fn (int $processed, ?int $total) => printf("%d/%d bytes\n", $processed, $total ?? -1),
);

$hasher->hashStream($stream, 'sha256', 65536, $progress);
```

`progress` is a no-op when `null`, so existing calls keep working unchanged. The core
only calls `onBytes()` — rendering (CLI bars, async notifications) lives in the
adapter that implements the port.

### PSR-7 stream hashing

`ItPack\HashIt\Adapter\PsrStreamHasher` hashes a `Psr\Http\Message\StreamInterface`
body in chunks without loading it fully into memory, and never detaches the stream,
so the same request/response body can be hashed *and* forwarded:

```php
use ItPack\HashIt\Adapter\NativeContextFactory;
use ItPack\HashIt\Adapter\PsrStreamHasher;

$hash = (new PsrStreamHasher(new NativeContextFactory()))->hash($request->getBody(), 'sha256');
```

The adapter depends only on `psr/http-message` (added as a `suggest`ed package) and
the core ports; it produces a plain `HashInterface`. **SRI / base64 digest formatting
is intentionally out of scope here** and belongs with the dedicated formatters (NF-C1).

### Observability

The hashers can emit a structured event stream through the core
`ItPack\HashIt\Contract\HashingObserverInterface` port, without the core depending on any
observability SDK. Inject an observer via the composition root and it receives a
`HashObservation` (`started` → `progress` → `completed`/`failed`) for every operation,
each carrying a correlation `id` so an adapter can pair a span's start with its end:

```php
use ItPack\HashIt\Adapter\NativeHashIt;

$hasher = NativeHashIt::create($myObserver); // $myObserver implements HashingObserverInterface
$hasher->hash('hello');                       // -> [started, completed] on the observer
```

The observer parameter defaults to `null`, so existing code keeps working unchanged.
Concrete adapters (OpenTelemetry traces, CloudEvents) are added separately and live in
the `Adapter\*` namespace — the core only defines the port and the `HashOperation`
enum / `HashObservation` value object.

### OpenTelemetry tracing (optional)

`ItPack\HashIt\Adapter\Observability\OtelHashingObserver` turns those observations into
OpenTelemetry spans: each operation becomes an `INTERNAL` span named after the operation
(`HASH`, `HMAC_FILE`, …) carrying `hashit.algorithm`, `hashit.bytes_total`, per-chunk
`hashit.bytes_processed`, and on completion `hashit.duration_ms` / `hashit.result_urn`
(`Ok` status) — or `recordException` + `Error` status on failure. It depends only on the
OTel **API**, so the SDK, tracer provider and OTLP exporter (`OTEL_*` env) stay owned by
the host app. Install `open-telemetry/api` and inject the observer yourself — the library
never instantiates it automatically:

```php
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Adapter\Observability\OtelHashingObserver;

$hasher = NativeHashIt::create(new OtelHashingObserver()); // uses the app's global TracerProvider
```

### CloudEvents emitter (optional)

`ItPack\HashIt\Adapter\Observability\CloudEventsHashingObserver` turns the same
observations into CNCF CloudEvents (spec 1.0): each completed operation becomes an
event of type `io.itpack.hashit.hash.completed.v1` whose JSON `data` carries
`algorithm`, `bytes`, `durationMs` and `urn`; each failure becomes
`io.itpack.hashit.hash.failed.v1` with the `error` message and class. Events are produced
only for terminal observations (`started`/`progress` are ignored) and are kept for
inspection via `collected()`; pass a PSR-18 client plus a broker URL to also POST them
(`Content-Type: application/cloudevents+json`). Like the OTel adapter it is optional —
install `cloudevents/sdk-php` and inject the observer yourself:

```php
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Adapter\Observability\CloudEventsHashingObserver;

$hasher = NativeHashIt::create(new CloudEventsHashingObserver()); // source defaults to hash-it://<host>
```

## Key derivation

Three families, behind small ports obtained from the composition root.

**HKDF (RFC 5869)** — turns strong but short input keying material into any amount
of keying material, bound by an `info` label. Built purely on the keyed HMAC surface,
so every derivation is observable when an observer is supplied:

```php
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;

$okm = NativeHashIt::kdf()->derive(
    $ikm,                       // input keying material
    $salt,                      // optional; omitted/empty => HashLen zero octets (RFC 5869 §3.1)
    42,                         // output length in bytes
    'encryption-key',           // info context label
    HashAlgorithm::SHA256,
);
```

**PBKDF2** — iteration-hardened, for stretching weak passwords:

```php
$derived = NativeHashIt::pbkdf2()->derive($password, $salt, iterations: 100_000, length: 32);
```

**Argon2id** — verify-oriented password hashing (no raw `derive` step; the salt and
costs are folded into the returned hash string):

```php
$hash = NativeHashIt::argon2()->hash($password);          // $argon2id$…
if (NativeHashIt::argon2()->verify($password, $hash)) { /* … */ }
```

## Structured / canonical hashing

`Hasher::hashInput()` deterministically hashes *structured* content — strings,
streams and files composed in a fixed order — so the same logical content always
digests identically regardless of how it was assembled. Inputs implement the
`HashInputInterface` port and write their canonical bytes through `writeTo()`:

```php
use ItPack\HashIt\HashInput\CompositeInput;
use ItPack\HashIt\HashInput\StringInput;
use ItPack\HashIt\HashInput\FileInput;

$hash = NativeHashIt::create()->hashInput(
    new CompositeInput([
        new StringInput('header'),
        new FileInput(NativeFilepathFileinfo::of($configPath)),
        new StringInput('footer'),
    ]),
);
```

The allowed input kinds (no exotic inputs — see roadmap §7):

| Input | Written bytes |
|-------|---------------|
| `StringInput` | the raw string bytes |
| `StreamInput` | the stream bytes, read in 64 KiB chunks (`DEFAULT_CHUNK_SIZE_BYTES`) |
| `FileInput` | the file bytes (opens the path and delegates to `StreamInput`) |
| `CompositeInput` | **length-prefixed framing** of its ordered children (below) |

**Framing (cross-implementation parity):** for each child of a `CompositeInput`,
write an **8-byte unsigned little-endian length** (`HashInputInterface::FRAME_LENGTH_BYTES`)
followed by the child's own framed bytes. Children are framed recursively, so nested
composites are canonical too. A *bare* `StringInput`/`StreamInput`/`FileInput` writes
its raw bytes with **no** prefix — consequently `hashInput(new StringInput('x'))` equals
`hash('x')`. Because every child is length-delimited, the same ordered sequence always
produces the same digest, while a different order (or different child boundaries) produces
a different digest. The frame width is fixed at 8 bytes; do not change it without bumping
the wire format, since existing digests would no longer verify.

## Tree / chunked hashing

`NativeHashIt::treeHasher()` produces a **tree hash** of a large input: the input
is split into fixed-size chunks, each chunk is hashed independently, and the chunk
digests are combined with a stable, specified combiner into a *single* root digest
that is **distinct** from `hash()` of the same bytes. It is a strict generalization
of `hash()` — a single-chunk input returns the plain `hash()`.

```php
use ItPack\HashIt\HashInput\StringInput;

$tree = NativeHashIt::treeHasher();

// chunkSize >= input length  → identical to Hasher::hash()
$tree->treeHash(new StringInput($data), 1_048_576);

// chunkSize < input length    → a distinct tree hash
$tree->treeHash(new StringInput($data), 64 * 1024);
```

The input is any `HashInputInterface` (string / stream / file / composite), so
`treeHash()` works over in-memory data, files and streams without loading the
whole input into memory at once.

**Scheme & framing (must be recorded with the digest):** the tree hash is a pure
function of `(input bytes, chunkSize, algorithm)`. Full specification and the
rationale (why fixed-size chunking over BLAKE3-style SIMD chunking) are in
[`docs/ADR-0001-tree-hash-combiner.md`](./docs/ADR-0001-tree-hash-combiner.md).
In short:

- Each leaf = `H(chunk)` (raw binary digest, `outputBits/8` bytes).
- Each internal node combines two children as `H( frame(left) || frame(right) )`
  where `frame(x) = LE64(length(x)) || x`; the trailing odd node is promoted
  unchanged. Recurse to a single root.
- The **chunk size is part of the digest** — a different `chunkSize` yields a
  different tree hash. There is no single canonical size; record `chunkSize`
  alongside the value.

Output length equals the base algorithm's digest length, so a tree hash composes
with `Hash` / `HashInterface::equals`. The on-wire root is *not* compatible with
THEX or BLAKE3; it is HashIt's own stable scheme (cross-impl interop with a named
external spec would live in a separate package).

## Resumable (pausable) hashing

`NativeHashIt::resumable()` lets you hash a large stream in stages and **pause /
resume** across requests or processes. A checkpoint is a portable blob you persist;
resuming rebuilds the exact in-progress state — no re-hashing of what was already
consumed.

```php
use ItPack\HashIt\Adapter\NativeHashIt;

$resumable = NativeHashIt::resumable();

$handle = $resumable->start();           // default SHA-256
$handle->update($chunk1);
$checkpoint = $handle->checkpoint();      // persist this (e.g. to a store)

// …later, possibly a different process…
$resumed = $resumable->resume($checkpoint);
$resumed->update($chunk2);
$hash = $resumed->finalize();             // == Hasher::hash($chunk1 . $chunk2)
```

`checkpoint()` does **not** consume the live handle — you can keep `update()`-ing
the same handle afterwards. The checkpoint envelope carries the algorithm and the
binary flag, so `resume()` needs only the blob.

**Plain algorithms only.** Keyed / HMAC contexts cannot be serialized by the native
`hash` extension (it throws), so `checkpoint()` raises a `RuntimeException` for them
— HMAC resumption is intentionally not offered (it would also embed the secret key
in the persisted blob). Full spike + rationale:
[`docs/ADR-0002-resumable-hashing.md`](./docs/ADR-0002-resumable-hashing.md).

## Manifest verify & formatters

`HashManifest` is an immutable value object pairing one algorithm with a map of
`path → expected HashInterface`. `ManifestVerifier::verify($manifest, $hasher)` re-hashes
each path and returns a `VerificationReport` (`matched` / `mismatched` / `missing`),
comparing with the constant-time `HashInterface::equals()` and treating unreadable paths
as `missing`:

```php
use ItPack\HashIt\HashManifest;
use ItPack\HashIt\ManifestVerifier;

$manifest = (new HashManifest(HashAlgorithm::SHA256))
    ->with('/etc/hosts', $expected);

$report = (new ManifestVerifier())->verify($manifest, $hasher);
if (!$report->allMatched()) {
    $report->throwOnFailure(); // fail-closed RuntimeException
}
```

The text (de)serialization of manifests is **out of scope for the hashing core** (guardrail #7)
and lives in a walled `ItPack\HashIt\Format\*` namespace that may import only the core value
objects (`Hash`, `HashManifest`, `HashAlgorithm`) — never hashers or contexts (enforced by
`composer architecture`). Three formats ship:

| Formatter | Line shape | Algorithm |
|-----------|------------|-----------|
| `GnuSha256Format` | `<hash>  <path>` (or `<hash> *<path>`) | fixed SHA-256 |
| `BsdFormat` | `SHA256 (<path>) = <hash>` | from the prefix (any algorithm) |
| `SfvFormat` | `<path> <hash>` + `# algorithm <value>` header | from header, default SHA-256 |

Each is strict: malformed lines throw `InvalidArgumentException`, and `serialize()`→`parse()`
round-trips exactly. Hex digests are normalized to lowercase on parse.

## Algorithms

`HashAlgorithm` is a backed enum and the **single source of truth** for the algorithm
catalogue. HMAC-safe algorithms are marked with `#[HmacSafe]` and the safe set is *derived*
at runtime — there is no duplicated constant list to drift:

```php
HashAlgorithm::default();                   // HashAlgorithm::SHA256
HashAlgorithm::SHA256->isHmacSafe();        // true
HashAlgorithm::CRC32->isHmacSafe();         // false
HashAlgorithm::hmacSafeAlgorithms();        // list<HashAlgorithm> (44 cases)
```

> **Legacy algorithms:** MD2/MD4/MD5/SHA1 (and other legacy digests) remain selectable for
> interoperability with formats that require them. For security-sensitive use, prefer
> SHA-256 or stronger.

### Algorithm discovery

Beyond the enum, every hasher exposes a queryable **algorithm source** so callers can
enumerate, check and resolve identifiers without forking the enum — e.g. to plug in
custom or KMS-backed algorithms through a custom `AlgorithmSourceInterface`:

```php
$hasher = NativeHashIt::create();      // default NativeAlgorithmSource (all enum cases)
$hasher->algorithms();                 // list<HashAlgorithm>
$hasher->supports('sha256');           // true
$hasher->resolve('sha256');            // HashAlgorithm::SHA256

// extensible: inject your own catalogue
$custom = new MyKmsAlgorithmSource();
$hasher = NativeHashIt::create(null, $custom);
```

`NativeHashIt::keyedHasher()` and `NativeHashIt::hmacHasher()` accept the same optional
source. `CompositeAlgorithmSource` merges several sources and dedups by algorithm value.

## Why ports — and why the core never sees `HashContext`

The hashing state type (`\HashContext`) is a PHP implementation detail, not part of the
contract you own. The core (`src/`, outside `Adapter/`) speaks only to ports it defines:

| Port | Responsibility |
|------|----------------|
| `ContextFactoryInterface::build(string $algorithm): HashContextInterface` | create a context for an algorithm |
| `HashContextInterface` | `update(string): void` + `finalize(bool): string` |
| `FilepathInterface` | `__toString(): string` (a path the core can hash) |

The **only** file that touches PHP's `\HashContext` is `src/Adapter/NativeHashContext.php`.
To back `HashIt` with a different hashing implementation (a KMS, a message queue, a test
double), implement these ports and rebind them in `Adapter/NativeHashIt` — the core stays
untouched. That is the point of the ports & adapters split.

## Layout

```
src/
  Hash.php, HashInterface.php        immutable value object
  HashAlgorithm.php, HmacSafe.php    algorithm catalogue + #[HmacSafe] marker
  HashContextInterface.php           port
  ContextFactoryInterface.php        port
  FilepathInterface.php              port
  DirectoryListingInterface.php      port
  HasherInterface.php                port (the public hasher API)
  KeyedContextFactoryInterface.php   port (keyed/HMAC context factory)
  KeyedHasherInterface.php           port (keyed HMAC surface, mirrors HasherInterface)
  Hasher.php                         core service (depends only on ports)
  KeyedHasher.php                    core service (keyed HMAC, depends only on ports)
  Adapter/
    NativeHashContext.php            wraps PHP \HashContext
    NativeContextFactory.php         builds a NativeHashContext
    NativeFilepathFileinfo.php       SplFileInfo -> FilepathInterface
    NativeDirectoryListing.php       enumerates regular files -> FilepathInterface
    NativeHmacHasher.php             HMAC via hash_hmac()
    NativeKeyedContextFactory.php    keyed HMAC context via hash_init(..., HASH_HMAC, $key)
    NativeHashIt.php                 composition root (binds the adapters)
```

The core→adapter boundary is enforced by `composer architecture` (deptrac): the
core namespace cannot reference `Adapter\*`, and `NativeHashIt` is the single
allowed wiring point.

## Examples

Runnable, copy-pasteable examples that teach and showcase every feature, split into
**horizontal** modalities (strings, files, streams, bulk, verify, progress, the `Hash`
value object, algorithm catalogue & metadata) and **vertical** feature families (HMAC,
HKDF, PBKDF2, Argon2, structured/composite, tree, resumable, manifests & formats,
observability, PSR-7, a custom algorithm catalogue, and the composition root).

```bash
php examples/00-quick-start.php
php examples/horizontal/01-hash-strings.php
php examples/vertical/20-hmac.php
```

See [`examples/README.md`](examples/README.md) for the full index and what each example
demonstrates.

## Testing & quality gates

```bash
composer test:unit    # Codeception unit suite
composer analyse      # PHPStan
composer cs:check     # PHP-CS-Fixer
```

The package gates on a green Codeception suite, PHPStan at level 5 with a dead-code
detector, and PHP-CS-Fixer.

## Status

Usable. The full motivation, defects found, and the roadmap that produced this design live
in the monorepo-level review under `docs/` (`CODE_REVIEW.md`, `FINDINGS_*.md`).
