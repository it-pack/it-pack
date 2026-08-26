# PORTS.md

The public contracts of ConfigureIt.

This document answers: **what are the stable extension points?** Every port is an
interface under `src/Contract/`. Adapters (under `src/Adapter/`) and Bridges
(under `src/Bridge/`) implement them; Core (under `src/Core/`) depends only on
the ports, never on their implementations.

## Contents

1. [Philosophy](#1-philosophy)
2. [Core Ports](#2-core-ports)
3. [Port Relationships](#3-port-relationships)
4. [Lifecycle](#4-lifecycle)
5. [Dependency Rules](#5-dependency-rules)
6. [Extension Guidelines](#6-extension-guidelines)
7. [Examples](#7-examples)

---

## 1. Philosophy

ConfigureIt thinks in terms of **sources, documents, graphs, pipelines, schemas
and snapshots** — not files or formats. Formats and transports are adapters; the
Core reasons only about the ports below. Each port answers one question:

- **Source** — *where* does a value come from?
- **Reader** — *how* is a resource turned into a document?
- **Loader** — *how* is a document parsed into a value tree?
- **Resolver** — *what* is the final, validated, frozen configuration?
- **Schema** — *is* the graph valid?
- **Processor** — *how* is the graph transformed between load and freeze?
- **Merger** — *how* do overlapping sources combine (last-wins)?
- **Cache** — *can* a resolved artifact be reused cheaply?
- **GraphBuilder** — *how* is a document flattened into nodes?
- **SnapshotFactory** — *how* is the frozen snapshot materialized?

---

## 2. Core Ports

### ConfigurationSource — `ConfigurationSourceInterface`

- **Responsibility:** enumerate the resources that make up a configuration (files,
  ENV, HTTP, vault, programmatic, …).
- **May depend on:** Contracts, ValueObjects (`Format`, `ConfigurationResource`).
- **May not depend on:** other Sources, Readers, Loaders, frameworks, vendored libs.
- **Typical adapters:** `FileSource`, `EnvSource`, `DotenvSource`, `ProgrammaticSource`,
  `HttpSource`, `VaultSource`, `ConsulSource`, `EtcdSource`, `AwsParameterStoreSource`,
  `GcpSecretManagerSource`, `AzureAppConfigSource`, `KubernetesSecretSource`,
  `DockerSecretsSource`, `ConfigMapSource`.
- **Example:**
  ```php
  interface ConfigurationSourceInterface
  {
      /** @return iterable<ConfigurationResourceInterface> */
      public function discover(): iterable;
  }
  ```

### ConfigurationReader — `ConfigurationReaderInterface`

- **Responsibility:** read a resource into a `ConfigurationDocument` (decode + normalize).
- **May depend on:** Contracts, ValueObjects, `ConfigurationLoaderInterface` (to parse).
- **May not depend on:** specific file extensions, frameworks, vendored libs.
- **Typical adapters:** `CompositeReader` (fans a resource set out to per-format loaders),
  `DecryptingReader` (decorates another reader with a `DecrypterInterface`).
- **Example:**
  ```php
  interface ConfigurationReaderInterface
  {
      /** @return iterable<ConfigurationDocumentInterface> */
      public function read(ConfigurationResourceInterface $resource): iterable;
  }
  ```

### ConfigurationLoader — `ConfigurationLoaderInterface` / `ConfigurationLoaderFactoryInterface`

- **Responsibility:** parse one document's payload (a format-specific string/array) into a
  plain `array` tree keyed by dotted path. The factory selects a loader by `Format`.
- **May depend on:** Contracts, ValueObjects (`Format`).
- **May not depend on:** merging, schemas, pipelines, frameworks, vendored libs.
- **Typical adapters:** `JsonLoader`, `YamlLoader`, `TomlLoader`, `NeonLoader`, `IniLoader`,
  `XmlLoader`, `EnvLoader`, `DotenvLoader`, `FrontMatterLoader`, `PhpLoader`.
- **Example:**
  ```php
  interface ConfigurationLoaderInterface
  {
      public function load(string $raw): array;
      public function supports(Format $format): bool;
  }
  ```

### ConfigurationResolver — `ConfigurationResolverInterface`

- **Responsibility:** coordinate the whole pipeline (load → normalize → merge →
  validate → resolve → freeze → snapshot) and produce the immutable snapshot.
- **May depend on:** Contracts, ValueObjects, Core builders (composition root only).
- **May not depend on:** Adapters/Bridges directly (wiring is injected via `ConfigurationWiringInterface`).
- **Typical adapters:** `ConfigurationResolver` (Core), `CachingResolver` (decorates with a `ConfigurationCacheInterface`).
- **Example:**
  ```php
  interface ConfigurationResolverInterface
  {
      public function resolve(): ConfigurationSnapshotInterface;
  }
  ```

### ConfigurationSchema — `ConfigurationSchemaInterface`

- **Responsibility:** validate a resolved graph against a definition; translate vendor
  schema errors into the domain `ValidationResult`.
- **May depend on:** Contracts, ValueObjects (`ValidationResult*`).
- **May not depend on:** Core resolver internals, frameworks.
- **Typical adapters:** `NativeConfigurationSchema` (wraps `nette/schema`).
- **Example:**
  ```php
  interface ConfigurationSchemaInterface
  {
      public function validate(ConfigurationGraphInterface $graph): ValidationResultInterface;
  }
  ```

### ConfigurationProcessor — `ConfigurationProcessorInterface`

- **Responsibility:** deterministically transform the graph (one stage of the pipeline).
- **May depend on:** Contracts, ValueObjects; optionally `ProvenanceAwareProcessorInterface`
  to record its stage in node provenance.
- **May not depend on:** other processors' order, frameworks, vendored libs.
- **Typical adapters:** `DefaultsProcessor`, `CastingProcessor`, `ValidationProcessor`,
  `IncludeProcessor`, `VariableProcessor`, `PlaceholderProcessor`, `FreezeProcessor`.
- **Example:**
  ```php
  interface ConfigurationProcessorInterface
  {
      public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface;
  }
  ```

### ConfigurationMerger — (implicit)

- **Responsibility:** combine overlapping sources so that later (higher-priority) sources
  win (last-wins). There is no standalone interface; merging is performed by
  `ConfigurationPipeline` as documents are flattened into nodes (each node carries the
  priority of the source that produced it). A dedicated `ConfigurationMergerInterface`
  may be extracted later if merge strategy becomes pluggable.
- **May depend on:** Contracts, ValueObjects.
- **May not depend on:** formats, frameworks, vendored libs.
- **Typical implementations:** `ConfigurationPipeline` (default last-wins), `LayerResolver`
  (layered composition).

### ConfigurationCache — `ConfigurationCacheInterface`

- **Responsibility:** store and restore compiled artifacts (the frozen graph + snapshot)
  keyed by a content/version hash, so re-resolution is skipped when inputs are unchanged.
- **May depend on:** Contracts, ValueObjects.
- **May not depend on:** specific backends' frameworks (the backend is injected).
- **Typical adapters:** `CompilerCache` (filesystem), `MemoryCache`, `RedisCache`.
- **Example:**
  ```php
  interface ConfigurationCacheInterface
  {
      public function get(string $key): ?ConfigurationSnapshotInterface;
      public function put(string $key, ConfigurationSnapshotInterface $snapshot): void;
  }
  ```

### ConfigurationGraphBuilder — (implicit)

- **Responsibility:** flatten a `ConfigurationDocument` into `ConfigurationNode`s, attaching
  structured `Provenance` (the single mint point for load-time provenance). No standalone
  interface; it is `DocumentNormalizer` implementing `DocumentNormalizerInterface`.
- **May depend on:** Contracts, ValueObjects (`Provenance`, `Format`, `Key`).
- **May not depend on:** schemas, pipelines, frameworks, vendored libs.
- **Typical adapters:** `DocumentNormalizer`.

### ConfigurationSnapshotFactory — `ConfigurationSnapshotFactoryInterface`

- **Responsibility:** materialize the immutable, frozen `ConfigurationSnapshot` from the
  resolved graph (optionally widening to a typed snapshot when a schema enumerates scalars).
- **May depend on:** Contracts, ValueObjects.
- **May not depend on:** sources, frameworks, vendored libs.
- **Typical adapters:** `ConfigurationSnapshotFactory`, `TypedSnapshotFactory`.
- **Example:**
  ```php
  interface ConfigurationSnapshotFactoryInterface
  {
      public function fromGraph(ConfigurationGraphInterface $graph): ConfigurationSnapshotInterface;
  }
  ```

---

## 3. Port Relationships

```
ConfigurationSource ──▶ ConfigurationResource
        │
        ▼
ConfigurationReader (uses ConfigurationLoader by Format)
        │  ConfigurationDocument
        ▼
ConfigurationPipeline
   ├─ DocumentNormalizer (ConfigurationGraphBuilder) ──▶ ConfigurationGraph (nodes)
   ├─ ConfigurationProcessor*  (merge + transform; last-wins)
   ├─ ConfigurationSchema       (validate)
   ▼
ConfigurationResolver ──▶ ConfigurationSnapshotFactory ──▶ ConfigurationSnapshot
        │                                              (backed by ConfigurationCache)
```

---

## 4. Lifecycle

1. A `ConfigurationSource` `discover()`es resources.
2. A `ConfigurationReader` `read()`s each into a `ConfigurationDocument` (via a
   `ConfigurationLoader` chosen by `Format`).
3. `ConfigurationPipeline` flattens documents into nodes (`DocumentNormalizer`),
   then runs `ConfigurationProcessor`s (merge, defaults, casts, validation, …).
4. `ConfigurationSchema` validates the graph; `ConfigurationResolver` freezes it.
5. `ConfigurationSnapshotFactory` builds the immutable `ConfigurationSnapshot`,
   cached by `ConfigurationCache`.

---

## 5. Dependency Rules

These mirror `AGENTS.md` and MUST hold for every port and adapter:

- **Core** may depend on **Contracts** + **ValueObjects** only. Never Adapters/Bridges.
- **Adapters** may depend on **Core contracts** + **vendor libraries** (isolated here).
- **Bridges** may depend on **Core contracts** only (OpenTelemetry, etc.).
- **Contracts** must never reference concrete Adapters/Bridges or frameworks.
- Everything is replaceable and composable; use decorators/processors over inheritance.

---

## 6. Extension Guidelines

- To add a source: implement `ConfigurationSourceInterface` (and `ConfigurationResource`).
- To add a format: implement `ConfigurationLoaderInterface` and register it with the
  `ConfigurationLoaderFactory`.
- To add a transform: implement `ConfigurationProcessorInterface` (optionally
  `ProvenanceAwareProcessorInterface` to record your stage).
- To add a schema: implement `ConfigurationSchemaInterface`, translating vendor errors into
  `ValidationResult`.
- To add a cache: implement `ConfigurationCacheInterface`.
- To run external commands: implement `CommandRunnerInterface` (the default
  `NativeCommandRunner` passes the argument **array** straight to `proc_open` — no shell
  is ever invoked — and enforces a wall-clock deadline).
- To talk to remote KV backends: implement `RemoteKeyValueClient` over an injected
  `HttpClient`. **TLS/auth recipe:** encode transport security in the URI scheme
  (`consul+tls://…`, `etcd+tls://…` → upstream `https://`; plain schemes stay `http://`,
  anything else is rejected) and supply credentials by decorating `HttpClient` with a
  transport that adds auth headers (e.g. `X-Vault-Token`, bearer tokens, AWS SigV4) —
  clients never embed secrets themselves. The cURL transport deliberately does not follow
  redirects: authenticated headers must never be forwarded to a redirect target.
- Never put business logic in an adapter; adapters translate, Core decides.

---

## 7. Examples

See `examples/` for runnable source/format/schema/cache wiring, and
`src/Wiring/DefaultConfigurationWiring.php` for the composition root that binds the
Core to its default adapter implementations.
