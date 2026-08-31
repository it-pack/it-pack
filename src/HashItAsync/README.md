# hash-it-async

> Concurrent file hashing bridge for the ItPack semantic stack — fans
> [`it-pack/hash-it`](../../HashIt)'s `hashFile` across the
> [`it-pack/async-it`](../../AsyncIt) runtime.

This package is a **bridge**, not a fork: it owns no hashing logic of its own and
never alters HashIt's core (roadmap guardrail #8). It exists so that hashing many
files can be driven by AsyncIt's execution engine (fiber/context/telemetry/cancellation
model) without coupling HashIt to any async runtime.

## Why a separate package?

`NF-C2` (the "Async bridge" milestone) is, by design, a sibling package. HashIt stays
single-threaded and dependency-light; the concurrency wiring lives here and depends on
HashIt and AsyncIt via Composer `require` only. HashIt's quality gates are untouched.

## Usage

```php
declare(strict_types=1);

use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Filepath;
use ItPack\HashItAsync\ConcurrentFileHasher;

$runtime = AsyncRuntime::discover();
$hasher  = NativeHashIt::create();

$paths = [new Filepath('/a.bin'), new Filepath('/b.bin'), new Filepath('/c.bin')];

$map = (new ConcurrentFileHasher())->hashFiles($paths, $hasher, $runtime);
// $map is array<string, \ItPack\HashIt\HashInterface>, keyed by path, in input order.
```

`hashFiles(iterable<FilepathInterface> $paths, HasherInterface $hasher, AsyncRuntime $runtime, ?HashProgressInterface $progress = null): array<string, HashInterface>`

- Each file becomes **one AsyncIt execution** with its own `ReferenceExecutionScope`
  (keyed by the path string), so it inherits AsyncIt's execution context, telemetry and
  cooperative-cancellation model.
- The result map is keyed by the path string and **preserves the input iteration order**,
  matching a sequential `HasherInterface::hashFiles`.
- It reuses `HasherInterface::hashFile` **verbatim** — same digest, and the *same*
  `RuntimeException` HashIt throws for an unreadable file surfaces unchanged.
- An optional `HashProgressInterface` (NF-A4) reports progress once per completed file,
  with a running byte total when the input is a re-iterable array.

## Concurrency caveat

Each file is submitted to the runtime as an independent task via
`AsyncRuntime::run(...)`. Genuine cooperative interleaving depends on the selected
AsyncIt adapter yielding on I/O; with a synchronous reference adapter the tasks still
execute one-per-`run` but always under AsyncIt's execution model. The bridge's contract
(identical result map to sequential hashing, preserved order, unchanged exceptions) holds
across every adapter — which is exactly what the unit suite asserts.

## Architecture

`deptrac` enforces that the `Bridge` layer may depend only on `ItPack\HashIt` and
`ItPack\AsyncIt` — never past them into RunIt or any other sibling. HashIt and AsyncIt
are themselves walled.

## Quality gates

```bash
composer test:unit      # codeception Unit
composer analyse        # phpstan level 5
composer cs:check       # php-cs-fixer
composer architecture   # deptrac
```

These are the bridge's own gates (HashIt's gates are unaffected).
