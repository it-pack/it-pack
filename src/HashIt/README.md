# hash-it

> Part of the **It-Pack** family.

Hashes strings and files via PHP's `hash_*` extension, behind a small **hexagonal (ports & adapters)** API. The core depends only on interfaces it owns; the concrete PHP binding lives in the `Adapter` namespace and is wired in a single composition root.

## Install

```bash
composer require it-pack/hash-it
```

Requires PHP `^8.4` and the `hash` extension (`ext-hash`).

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
`ItPack\HashIt\HashingObserverInterface` port, without the core depending on any
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
