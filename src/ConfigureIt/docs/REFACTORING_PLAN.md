# ConfigureIt — Refactoring Plan

> Companion to `IMPLEMENTATION_PLAN.md`, `CHANGELOG.md` and the `architecture/decisions/`
> ADRs. This document records concrete refactoring candidates found during a detailed
> review of the Phase 1 implementation (`src/`), assessed against:
>
> - **SOLID** (SRP / OCP / LSP / ISP / DIP)
> - **DRY**
> - **Ports & Adapters** (ADR-0002)
> - **PHP 8.4+ language features**: Factories, WeakMap, WeakReference, Fibers,
>   Virtual Properties, Lazy Objects
>
> Each finding lists: location, principle, severity, current behaviour, proposed
> refactoring, and the ADR (if any) that ratifies it.

Severity: 🔴 strong candidate (real cohesion/coupling or duplication issue) ·
🟠 worthwhile (clarity / future-proofing) · 🟡 minor / hygiene.

---

## 1. SRP / OCP — `ConfigurationResolver` is a god-coordinator

- **Location:** `src/Core/ConfigurationResolver.php:49-98` (`resolve()`), `:100-109` (`loaderFor()`)
- **Principle:** SRP, OCP, DIP
- **Severity:** 🔴

`resolve()` performs five distinct responsibilities in one method:

1. discovery (`$source->discover()`)
2. reading (`$this->reader->read()`)
3. loader selection (`loaderFor()`)
4. document construction (`new ConfigurationDocument(...)` at `:61`)
5. schema validation + snapshot freezing

`loaderFor()` (`:100-109`) is a hand-rolled strategy loop. Every new format
(YAML, TOML, NEON — Phase 2) currently requires the resolver to iterate loaders;
this is the classic OCP violation: *extending formats should not mean modifying
the resolver*. The `new ConfigurationDocument(...)` at `:61` also means the Core
resolver constructs a Core value object inline, mixing orchestration with
object construction.

- **Proposed refactoring:**
  - Extract a `LoaderResolver` / **`LoaderFactory`** port (`Format → Loader`) that
    owns the `supports()` loop. The resolver depends on the factory (DIP), not on
    iterating `iterable<LoaderInterface>`. → ratified by **ADR-0006**.
  - Extract a `DocumentFactory` (or `ReaderLoaderChain`) that turns a
    `(Resource, raw)` pair into a `ConfigurationDocumentInterface`. Keeps the
    resolver's `resolve()` as pure orchestration.
  - Keep `resolve()` as the composition root only (ADR-0002 / AGENTS.md
    "Core may depend on Contracts, not on how loaders are found").

- **ADR:** [ADR-0006 Loader Factory & Registry](#adr-0006)

---

## 2. DRY — `ConfigurationSnapshot::get()` and `has()` duplicate descent logic

- **Location:** `src/Core/ConfigurationSnapshot.php:34-48` (`get`) and `:50-64` (`has`)
- **Principle:** DRY
- **Severity:** 🔴

Both methods re-implement the identical dotted-key descent (`explode('.')` +
`foreach` + `is_array`/`array_key_exists` guard). Any change to path semantics
(e.g. escaping, wildcard, array-index segments) must be made in two places, and
they can drift.

- **Proposed refactoring:**
  - Introduce a single private `descend(string $path): ?array{cursor: mixed, found: bool}`
    helper, or better a `Path` value object (see §6) with `Path::get($tree)` /
    `Path::has($tree)`.
  - `get()` returns `cursor ?? $default`; `has()` returns `found`.

---

## 3. DRY — dotted-key tree walking is implemented three times

- **Location:**
  - `src/Core/ConfigurationSnapshot.php:34-64` (read descent)
  - `src/Core/ConfigurationGraph.php:80-89` (`toArray` rebuild) + `:94-109` (`assign`)
  - `src/Core/ConfigurationPipeline.php:77-98` (`flatten` builds dotted keys)
- **Principle:** DRY
- **Severity:** 🔴

Three separate pieces of code understand "a dotted key maps to a nested array".
`ConfigurationGraph::assign()` uses `by-reference` recursion to *write*; the
snapshot *reads*; the pipeline *constructs* the dotted key from a prefix. All three
encode the same mental model of the configuration key space.

- **Proposed refactoring:**
  - A shared `Key` / `Path` value object (immutable, validated) with:
    - `Key::fromSegments(array): self`
    - `Key::child(string): self`
    - `Key::get(array $tree): mixed` / `Key::has(array $tree): bool`
    - `Key::set(array &$tree, mixed $value): void`
  - `ConfigurationPipeline::flatten` produces `Key` objects; `ConfigurationGraph`
    indexes by `Key::toString()`; `ConfigurationSnapshot` reads via `Key::get`.
  - → ratified by **ADR-0009**.

---

## 4. DRY / OCP — loader selection is a manual strategy registry

- **Location:** `src/Core/ConfigurationResolver.php:100-109`; mirrored intent in every
  `Loader::supports()` (`src/Adapter/{PhpLoader,JsonLoader}.php`)
- **Principle:** DRY, OCP
- **Severity:** 🟠

Every loader repeats `public function supports(Format $f): bool { return $f === Format::X; }`.
The resolver repeats the loop that scans for a supporting loader. This is the
textbook "replace explicit conditionals / loops with a registry" refactoring.

- **Proposed refactoring:**
  - A `LoaderFactory` (port) built from `iterable<LoaderInterface>`, exposing
    `loaderFor(Format): LoaderInterface` (throws `ResolutionException` when none).
  - Optionally an attribute-driven catalogue (`#[Supports(Format::YAML)]`) so the
    registry is assembled by reflection — mirroring the "attribute-driven single
    source of truth" pattern the `HashIt` package uses for its algorithm catalogue
    (see `src/HashIt/README.md` and `docs/ROADMAP.md`).
  - → ratified by **ADR-0006**.

---

## 5. SRP — `ConfigurationPipeline` mixes normalisation with pipeline execution

- **Location:** `src/Core/ConfigurationPipeline.php:37-71` (`run`) + `:77-110` (`flatten`/`isAssociative`)
- **Principle:** SRP
- **Severity:** 🟠

`run()` does two unrelated things: (a) flatten each document into `ConfigurationNode`s
(the *normalise* stage), and (b) apply the registered processors (the *pipeline*
stage). `flatten()` also re-implements associative-array detection (`:103-110`),
which is generic array logic, not pipeline logic.

- **Proposed refactoring:**
  - Extract a `DocumentNormalizer` / `NodeFactory` (port) responsible for
    `ConfigurationDocumentInterface → list<ConfigurationNodeInterface>` using the
    shared `Key` abstraction (§3/§6).
  - `ConfigurationPipeline::run()` then becomes: `nodes = normalizer->normalize($doc); graph->put(...);` then processors.
  - `isAssociative()` becomes a `@pure` helper on a small `Arrays` utility or the
    `Key` value object.
  - → ratified by **ADR-0010**.

---

## 6. Ports & Adapters — shared `Key`/`Path` abstraction is missing from Core

- **Location:** cross-cutting (see §2, §3)
- **Principle:** DRY, Ports & Adapters (ADR-0002)
- **Severity:** 🟠

The dotted-key concept is a first-class domain concept (it appears in the
ubiquitous language: `database.host`, `app.debug`) yet it has no type. Strings are
passed everywhere. A `Key` value object would be a Core concept, no adapter
dependency, and would centralise escaping/segmentation decisions before Phase 2
adds array-index segments (`cache.pools[0].dsn`).

- **Proposed refactoring:** add `src/Core/Key.php` (or `src/ValueObject/Key.php`),
  immutable, with segment handling. → **ADR-0009**.

---

## 7. Lazy Objects / Virtual Properties — parsed documents and nodes are eager

- **Location:**
  - `src/Core/ConfigurationDocument.php` (stores full `toArray()` eagerly)
  - `src/Core/ConfigurationGraph.php:80-89` (`toArray()` rebuilds the whole tree on
    every call — called by `validate()` *and* `fromGraph()` in the resolver)
  - `src/Adapter/PhpLoader.php:28-52` (writes + `require`s a temp file per `load()`)
- **Principle:** performance / Lazy Objects / Virtual Properties
- **Severity:** 🟠

`ConfigurationGraph::toArray()` is invoked twice per resolution (schema validation
+ snapshot freeze) and rebuilds the entire nested tree each time. `PhpLoader`
materialises a temp file and `require`s it on every load. For large configs or
hot-reload (Live Resolution, `MODEL.md` "Future Concepts") this is wasteful.

- **Proposed refactoring:**
  - `ConfigurationNode` could hold its *value* behind a **Lazy Object / GhostObject**
    (`PHP 8.4` `newInstanceWithoutConstructor` + `LazyObjectInterface`) so that
    expensive values (e.g. decoded sub-trees, secret references) are only realised
    on first access. Useful when a node points at a `Vault` reference (`MODEL.md`).
  - `PhpLoader` could cache the parsed array per `resource->uri()` in a **WeakMap**
    (see §8) keyed by the resource, so repeated resolution (e.g. tests, live reload)
    reuses the document without re-`require`-ing.
  - `ConfigurationGraph::toArray()` could be memoised (cleared on `put()`), or the
    snapshot could be built incrementally rather than re-walking.
  - `ExecutionContext`/`Occurrence`/`Observation`/`ConfigurationResource` already use
    constructor-promoted readonly properties — good **virtual-property** citizens;
    no change needed, but they set the precedent for nodes.

- **ADR:** [ADR-0007 Lazy Documents, Caching & Weak References](#adr-0007)

---

## 8. WeakMap / WeakReference — provenance and document caching

- **Location:**
  - `src/Execution/ResolutionReporter.php:22-40` (`occurrences`/`observations` grow unbounded `list`s)
  - `src/Core/ConfigurationResolver.php:53-67` (documents rebuilt from resources each `resolve()`)
- **Principle:** WeakMap / WeakReference
- **Severity:** 🟡

The reporter retains every occurrence for the whole execution — fine for a single
resolution, but if a long-lived resolver is reused (Live Resolution / watchers),
occurrences accumulate. Document parsing is also repeated when the same
resource is resolved again.

- **Proposed refactoring:**
  - Attach per-source provenance via a `WeakMap<ConfigurationSourceInterface, list<Occurrence>>`
    so reporters can associate events with a source without keeping the source
    alive.
  - Cache parsed `ConfigurationDocumentInterface` per resource uri in a `WeakMap`
    inside the loader factory (§1/§4), keyed by the `ConfigurationResourceInterface`
    object (which is cheap and immutable), so GC can reclaim when the resource is
    dropped.
  - → **ADR-0007**.

---

## 9. Fibers — synchronous resolution blocks on async sources

- **Location:** `src/Contract/ConfigurationSourceInterface.php` (`discover(): iterable`),
  `src/Contract/ConfigurationReaderInterface.php` (`read(): string`)
- **Principle:** Fibers (async sources), OCP
- **Severity:** 🟡 (future — Phase 5/8: HTTP, S3, Vault, Redis)

Today `discover()`/`read()` are synchronous. The `VISION.md` source list includes
HTTP, S3, Vault, Redis, Consul, Etcd — inherently async. A blocking resolver will
stall the host runtime.

- **Proposed refactoring (future):**
  - Introduce an async port variant `AsyncConfigurationSourceInterface` /
    `AsyncConfigurationReaderInterface` whose `discover()`/`read()` suspend a
    **Fiber** and resume with the result, allowing concurrent fetching of multiple
    sources. The resolver would `Fiber::suspend()` until all sources settle, then
    merge — preserving deterministic priority-merge because the merge is order-based.
  - Keep the sync ports as the default; the async ports are adapters (ADR-0002).
  - → **ADR-0008**.

---

## 10. Factory consolidation — `Format::fromString` + per-loader `supports()`

- **Location:** `src/ValueObject/Format.php:40-52` (`fromString`), every adapter `supports()`
- **Principle:** Factories, DRY
- **Severity:** 🟡

`Format::fromString()` is a string→enum factory (good). The loader `supports()`
methods are a parallel manual dispatch. Consolidating both behind a single
`LoaderFactory` (§1/§4) removes the second dispatch table and gives one place to
register formats.

- **Proposed refactoring:** see §1 / §4 / **ADR-0006**.

---

## 11. ISP — `ConfigurationDocumentInterface` and `ConfigurationGraphInterface` are cohesive

- **Location:** `src/Contract/ConfigurationDocumentInterface.php`, `src/Contract/ConfigurationGraphInterface.php`
- **Principle:** ISP
- **Severity:** 🟡 (positive finding)

These interfaces are small and single-purpose — no ISP violation. Noted as a
*good* example to preserve while refactoring (do not let §5's `NodeFactory` bloat
the graph interface).

---

## Refactoring sequence (recommended order)

| # | Finding | ADR | Effort | Pays off for |
|---|---------|-----|--------|--------------|
| 1 | §3 `Key`/`Path` value object | ADR-0009 | M | §2, §5, Phase 2 array-index keys |
| 2 | §1 `LoaderFactory` port | ADR-0006 | M | Phase 2 formats, §4, §10 |
| 3 | §5 split `DocumentNormalizer` from `Pipeline` | ADR-0010 | M | SRP, testability |
| 4 | §2 snapshot shared descent (uses `Key`) | — | S | DRY |
| 5 | §7 Lazy Objects + memoised `toArray()` | ADR-0007 | L | Live Resolution, big configs |
| 6 | §8 WeakMap caching | ADR-0007 | M | repeated resolution, reporters |
| 7 | §9 Fibers for async sources | ADR-0008 | L | Phase 5/8 sources |

Each step keeps the existing 47-test suite green and must keep PHPStan at level 9.

---

## Verification gate for refactors

- PHPStan level 9: 0 errors (existing gate).
- `php-cs-fixer`: 0 files (existing gate).
- Codeception Unit suite: remains green; add/extend tests for the new `Key`,
  `LoaderFactory`, `DocumentNormalizer` (unit) and `WeakMap`/`LazyObject` behaviour
  (with fixtures + `@covers`).
- No adapter may import a Core class it shouldn't, and no Core class may import a
  vendor type (ADR-0002 preserved).
