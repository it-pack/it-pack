# HashIt examples

Runnable, copy-pasteable examples that teach and showcase HashIt's features, split
along two axes:

- **Horizontal** — the *input & output modalities* that apply across almost every
  hashing path (strings, files, streams, bulk, verify, progress, the `Hash` value
  object, and the algorithm catalogue & strength metadata).
- **Vertical** — the *distinct feature families* that extend the core in their own
  dimension (keyed/HMAC, key derivation, password hashing, structured hashing,
  tree hashing, resumable hashing, manifests & formats, observability, PSR-7, a
  custom algorithm catalogue, and the composition root).

Every example is standalone (it loads Composer's autoloader via `_support.php`) and
writes throwaway temp files that are cleaned up on exit.

## Run an example

```bash
php examples/00-quick-start.php
php examples/horizontal/01-hash-strings.php
php examples/vertical/20-hmac.php
```

Run them all (sequentially) with:

```bash
for f in examples/00-quick-start.php examples/horizontal/*.php examples/vertical/*.php; do
  echo "===== $f ====="; php "$f";
done
```

> The observability and PSR-7 examples guard their optional dependencies
> (`open-telemetry/api`, `cloudevents/sdk-php`, `psr/http-message`) with
> `class_exists` / `interface_exists` and print a friendly note when a dependency
> is absent, so the whole set runs on a minimal install.

## Horizontal — modalities

| File | Teaches |
|------|---------|
| `00-quick-start.php` | One hasher, many algorithms, content-addressable URNs, a taste of HMAC |
| `horizontal/01-hash-strings.php` | Algorithms by enum/string, hex vs raw digest, digest length |
| `horizontal/02-hash-files.php` | `hashFile()`, file == bytes, fail-closed on missing files |
| `horizontal/03-hash-streams.php` | `hashStream()` chunked + progress, seekable vs non-seekable streams |
| `horizontal/04-hash-files-and-directory.php` | `hashFiles()` and `hashDirectory()` bulk hashing |
| `horizontal/05-verify.php` | Timing-safe `verify()` / `verifyBinary()`, wrong-algorithm mismatch |
| `horizontal/06-progress.php` | `HashProgress` reporting on a large payload |
| `horizontal/07-hash-value-object.php` | `Hash`: equality, metadata, URN, `Hash::create()` |
| `horizontal/08-algorithm-catalogue.php` | `algorithms()` / `supports()` / `resolve()`, enum subsets |
| `horizontal/09-algorithm-strength-metadata.php` | `#[AlgorithmMetadata]` / `#[HmacSafe]` — read-only strength info |

## Vertical — feature families

| File | Teaches |
|------|---------|
| `vertical/20-hmac.php` | Keyed/HMAC surface (one-shot, binary, file, verify, determinism) |
| `vertical/21-key-derivation-hkdf.php` | HKDF (RFC 5869) — extract/expand, info & salt binding, length cap |
| `vertical/22-key-derivation-pbkdf2.php` | PBKDF2 iteration-hardened password stretching |
| `vertical/23-password-argon2.php` | Argon2id opaque hashes + constant-time verify |
| `vertical/24-structured-composite-hashing.php` | `hashInput()` + `CompositeInput` deterministic framing |
| `vertical/25-tree-hashing.php` | Tree/chunked hashing (NF-D1): a distinct root, chunkSize-sensitive |
| `vertical/26-resumable-hashing.php` | Resumable hashing (NF-D2): checkpoint, persist, resume across processes |
| `vertical/27-manifests-and-formats.php` | `HashManifest` + BSD/GNU-SHA256/SFV formats + `ManifestVerifier` |
| `vertical/28-observability.php` | Custom observer + OpenTelemetry & CloudEvents adapters |
| `vertical/29-psr7-streams.php` | Hashing a PSR-7 `StreamInterface` without detaching it |
| `vertical/30-custom-algorithm-source.php` | Pluggable `AlgorithmSourceInterface` policy + composition |
| `vertical/31-composition-root.php` | Hexagonal wiring: only `NativeHashIt` knows the adapters |

## `_support.php`

Shared scaffolding (autoload, temp-file/dir helpers, a `banner()` printer). It is not
part of the library — keep it only for running these examples.
