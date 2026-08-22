# RunIt — In-Depth Code Review

- **Subject:** `it-pack/src/RunIt` (current committed tree, post Phase 3)
- **Date:** 2026-08-22
- **Reviewer:** autonomous review (Kilo)
- **Scope:** features, architecture, maintenance, performance, maintainability,
  security, DX, SOLID, DRY, hexagonal architecture (ports/adapters/factories),
  modern PHP leverage, interface coverage, complexity, control-flow & magic-value hygiene.
- **Method:** repo-wide static scans across all `src/` (378 PHP files) + targeted
  reading of representative files per layer + execution of quality gates in this
  environment (PHP 8.5). No source was modified.

---

## 1. Conceptual review checklist

The review assessed, at a conceptual (not line-level) level:

1. **Architectural integrity** of the hexagonal boundary — whether ports, adapters,
   and the dependency direction hold under DepTrac and in practice.
2. **Adherence to SOLID and DRY** across domain, application, and adapter layers.
3. **Appropriate leverage of modern PHP** (fibers, weak maps/refs, lazy objects,
   property hooks, traits, `readonly`) without overengineering.
4. **Interface-backed design and control-flow/complexity discipline** — every class
   behind a contract, guard-clause/match style, bounded constructors, no magic values.
5. **Security, performance, and developer-experience posture** as first-class concerns.
6. **Quality-gate effectiveness and maintainability risk** — whether the gates actually
   pass and catch regressions.

---

## 2. Summary scorecard

| Dimension | Verdict | Evidence |
|---|---|---|
| Hexagonal architecture / ports & adapters | ✅ Strong | DepTrac **0 violations** (2273 allowed edges) |
| Dependency direction | ✅ Strong | Layer ruleset enforced; adapters depend inward |
| SOLID | ✅ Strong | SRP/OCP/ISP/DIP visible; 143 small interfaces |
| DRY | △ Adequate | Behavior traits good; minor VO-validation inconsistency |
| Interface coverage | △ One gap | 210/211 classes backed or exempt; `HttpExecutionContext` not |
| Control-flow / complexity | ✅ Strong | **0** `else`/`elseif`, **0** `switch`; ctor-width rule active |
| Magic strings / numbers | ✅ Strong | Context keys centralized; 370 `@see` vocab refs |
| Modern PHP (readonly/enum/fiber/weakmap/trait) | ✅ Strong | 112 `readonly`, 14 enums, 32 `Fiber`, 15 `WeakMap`, 10 traits |
| Modern PHP (lazy objects / property hooks) | ✗ Gap | **0** lazy objects; hooks under-leveraged |
| Security | ✅ Strong | Deny-by-default redaction, bounds, recursion guards |
| Performance | ✅ Strong | Monotonic clock port, bounded buffers, no hot-path reflection |
| Developer experience | ✅ Strong | Immutable builder, typed exceptions, vocab links |
| Quality gates (cs/deptrac/api) | ✅ Pass | cs 0/508; deptrac 0; api clean |
| Quality gate (phpstan `analyse`) | ✗ Fails | 20 errors: 15 `shipmonk.deadMethod` + **5 genuine** |
| Mutation / infection | ⚠ Not run | Codeception `di` bootstrap failure (pre-existing) |

Legend: ✅ strong · △ adequate · ✗ gap · ⚠ blocked.

---

## 3. Detailed findings

### 3.1 Architecture & hexagonal boundaries

- **Dependency direction holds.** DepTrac reports **0 violations** across **2273**
  allowed edges. The layer ruleset (`Common ← Contract/Port ← Domain ←
  Application/SDK ← Adapters`) is enforced and the `ForbidDomainImportsAdaptersRule`
  custom PHPStan rule backs it at the call level.
- **Ports are explicit.** External effects live behind `Contract/Port/*`
  (`ArtifactPublisherInterface`, `ContextStorageInterface`, `MonotonicClockInterface`,
  `SerializerInterface`, `SnapshotStoreInterface`, `IdentifierGeneratorInterface`).
  Adapters translate (Psr, CloudEvents, OpenTelemetry, Swoole, Fiber, Memory, Null,
  Callback, Common) and depend inward — no core depends on an adapter.
- **Factories are sparse; assembly is concentrated.** Only three factory types exist:
  `Domain/Factory/StringIdentifierFactory`, `Application/Factory/ExecutionFactory`,
  `Application/Factory/ExecutionSnapshotFactory`. The bulk of wiring lives in
  `SDK/Builder/RunItBuilder` (immutable copy-on-write builder, 24 `with*` seams,
  validates required ports at `build()`) and `Application/Runner/DefaultExecutionRunner`
  (composition root, 24 collaborators, `MaxConstructorParametersRule`-exempt). This is a
  legitimate builder + composition-root pattern, but the runner's width is an accepted
  exception rather than a decomposed design — see §4.2.
- **One boundary leak.** `Adapter/Psr/Http/HttpExecutionContext` is an `@api` DTO that
  carries HTTP-derived execution inputs but has **no contract** (see §3.4).

### 3.2 SOLID

- **SRP:** Classes are small and focused. Median method count is low; the largest are
  `RunItBuilder` (24 builder methods), `BufferedArtifactPublisher` (22 tightly-related
  buffering methods — overflow strategies are distinct helpers, not a god object), and
  `ExecutionScope` (13). No class exhibits mixed responsibilities.
- **OCP:** Publisher composition is decorator-based — `RedactingArtifactPublisher` wraps
  `SamplingArtifactPublisher` wraps the configured publisher, assembled in
  `RunItBuilder::build()`. Extension adds a new decorator without modifying existing ones.
- **LSP / ISP:** 143 fine-grained interfaces; value objects implement contracts
  (e.g. `ExecutionSnapshot implements ExecutionSnapshotInterface`). No fat interfaces observed.
- **DIP:** Application services and SDK depend on contracts. The single leak is the
  `HttpExecutionContext` DTO (§3.4).

### 3.3 DRY

- **Strong where it matters.** `Behavior/*` traits centralize cross-cutting value-object
  and recorder behavior (`ValidatedStringValueBehavior`, `TerminalStatusBehavior`,
  `TimestampedBehavior`, `StringIdentifierBehavior`, `InstantClockBehavior`, …). These are
  focused and interface-harmonized — exactly the trait style AGENTS.md prescribes.
- **Inconsistency in value-object validation.** Seven domain value objects use the
  `ValidatedStringValueBehavior` trait; seven others instantiate validators inline in
  their constructors (e.g. `new ContextValueValidator()`, `new AttributeValidator()` in
  `ContextKey`, `ContextEntry`, `Occurrence`, `Snapshot`, `AttributeKey`, …). Standardizing
  on the trait removes the duplication.
- **Minor duplication.** `new DefaultContextRedactionPolicy()` appears in both
  `ExecutionDefinition` and `Execution`; a shared default (constant or injected) would
  centralize the choice.

### 3.4 Interface coverage

- **Enforced and nearly complete.** A custom `RequireInterfaceRule` (ADR-015 / M13.2)
  requires every concrete class to implement an interface, with a documented exemption set:
  the `Conformance` and `Behavior` directories plus 24 value-object / config / DTO / map
  files tracked in `docs/qa.md`. Repo-wide scan: **210 of 211** concrete classes are either
  interface-backed or exempt.
- **One genuine gap.** `Adapter/Psr/Http/HttpExecutionContext` is a `final readonly`
  `@api` DTO with no interface and is **not** in the exemption list. PHPStan flags it
  (`runit.requireInterface`). Because it is a public surface returned to consumers, it
  should expose a contract (e.g. `HttpExecutionContextInterface` in `Contract/Shared` or
  `Adapter/Psr/Contract`) so consumers depend on the abstraction, not the concrete adapter
  type. This is the highest-value single fix in the review.

### 3.5 Control-flow, complexity & magic values

- **`else`/`elseif`: 0** in code (one doc-comment hit only). The `ForbidElseRule` is active
  and effective; existing branches use guard clauses and `match`.
- **`switch`: 0.** All multi-way branching uses `match` or early returns.
- **Magic strings:** Context/correlation keys are centralized in
  `Contract/Shared/CorrelationKeys` (T3.4); a repo scan finds **zero** stray `ctx.` or
  `itpack.`/`runit.` literals outside `Contract/Shared` and doc examples. Occurrence-type
  naming follows the extensible-string + taxonomy convention (ADR-018). **370** `@see`
  vocabulary references demonstrate strong semantic alignment with `vocab/run-it.ttl`.
- **Constructor width:** `MaxConstructorParametersRule` (threshold 16) with three exempt
  files (`DefaultExecutionRunner`, `ExecutionScope`, `RunItBuilder`). Five data-carrying
  value objects exceed 8 parameters (`CloudEvent` 12, `Measurement` 11, `SecurityLimits` 10,
  `Observation` 10, `Timeout` 9) — acceptable for immutable carriers but candidates for
  named builders/factories to improve DX.
- **Complexity rule:** A cognitive-complexity threshold was deliberately deferred in T3.1
  (no native support at PHPStan 2.2.8; would flag many existing methods). Method counts
  remain small, so measured risk is low, but the discipline is enforced by convention, not
  by a gate.

### 3.6 Modern PHP leverage

- **`readonly`: 112 of 211** concrete classes — immutability is the default for artifacts,
  value objects, and DTOs. `RequireReadonlyForArtifactRule` enforces it for domain artifacts.
- **Enums: 14** for genuinely closed internal state sets (`ExecutionStatus`,
  `NestedExecutionMode`, `ObservationSeverity`, …).
- **Fibers: 32** uses; `Adapter/Fiber/FiberContextStorage` is exemplary — per-Fiber scope
  stacks held in a `WeakMap` keyed by the `Fiber` object, giving isolation *and* non-owning
  GC (no manual cleanup). `Adapter/Swoole/CoroutineContextStorage` mirrors this for coroutines.
- **`WeakMap`: 15** uses for object-keyed, non-owning runtime associations (context storage,
  caches). `WeakReference` is unused, which is fine — `WeakMap` is the recommended tool.
- **Traits: 10**, all confined to focused `Behavior/*` helpers (plus one OTel bridge helper),
  harmonized with interfaces.
- **Gaps (opportunities, not defects):**
  - **Lazy objects: 0.** AGENTS.md explicitly recommends lazy objects for *expensive optional
    infrastructure* (OpenTelemetry bridge, serialization caches, in-memory stores). None are
    used; expensive adapters are constructed eagerly even when unused. This is a missed
    performance/footprint optimization.
  - **Property hooks: under-leveraged.** Several value objects expose computed projections
    via getter methods where PHP 8.4 property hooks (`get`/`set`) would be more idiomatic and
    reduce boilerplate (AGENTS.md recommends hooks for value objects backed by contracts).

### 3.7 Security

- **Deny-by-default redaction** (ADR-012): `DefaultArtifactRedactor` + `ArtifactRedactionConfig`
  + `DefaultContextRedactionPolicy` redact before export. `SecurityLimits` bounds depth,
  serialization bytes, and cardinality. **Recursion guards** (ADR-014,
  `RecursivePublicationException`) prevent self-publication loops. **No arbitrary object
  serialization** (`ForbidArbitraryObjectSerializationRule`). Secrets are never exported.
- **Wall-clock discipline:** `ForbidDirectWallClockRule` forces time through
  `MonotonicClockInterface`.
- **Minor gap in a security path:** `DefaultArtifactRedactor::maskKeys()` and
  `maskThrowable()` declare untyped `array` parameters/returns (PHPStan
  `missingType.iterableValue`). In a redaction component, precise types
  (`array<string, mixed>` / `array<array-key, mixed>`) improve safety and satisfy
  `analyse` at level max.

### 3.8 Performance

- Monotonic clock port + `ForbidReflectionOnHotPathRule` keep hot paths allocation- and
  reflection-free. `BufferedArtifactPublisher` bounds buffers with explicit overflow
  strategies (`dropOldest`, `dropLowPriority`, `blockWithTimeout`). `WeakMap`-backed
  context storage avoids retention leaks across Fibers/coroutines. No hot-path serialization.
  Performance posture is strong and matches the AGENTS.md requirements.

### 3.9 Developer experience & maintainability

- **Immutable builder** with `with*` seams and validation at `build()` gives a safe,
  discoverable assembly API. Exceptions are descriptive and implement marker interfaces
  (`RunItExceptionInterface`, `InvalidValueExceptionInterface`, …). **370** `@see` vocabulary
  links aid navigation. `Conformance/*` provides framework-neutral verifiers/fixtures.
- **Dead-code notices:** `composer analyse` emits **15** `shipmonk.deadMethod` notices
  (e.g. `SecurityLimits::with*`, `ExecutedAs`, OTel map classes). These are mostly public
  fluent/API surfaces flagged as unused *internally*; they are accepted noise but warrant a
  periodic sweep. Four recorders' `$clock` property is flagged `property.onlyWritten`, but it
  **is** read by `InstantClockBehavior::now()` — this is a PHPStan trait-analysis limitation,
  not a real dead dependency (verify before acting).

### 3.10 Quality gates

- **`cs:check`** → **PASS** (0 of 508 files fixable).
- **`architecture` (DepTrac)** → **PASS** (0 violations, 2273 allowed).
- **`api:check`** → **PASS** (no backward-compatibility breaks; additive `CorrelationKeys`
  already baselined).
- **`analyse` (PHPStan level max)** → **FAILS** with **20** errors:
  - 15 `shipmonk.deadMethod` (accepted noise).
  - **5 genuine:** 1 × `runit.requireInterface` (`HttpExecutionContext`),
    4 × `missingType.iterableValue` (`DefaultArtifactRedactor`).
- **`mutation` / Infection** → **not executable** in this environment (Codeception `Unit`
  suite fails to bootstrap: `Service di is not defined` — pre-existing, not introduced by
  changes).

> **Important correction to prior status.** Earlier phase reports described PHPStan as
> "green on changed files." The full-tree `composer analyse` (level max, all of `src/`)
> does **not** pass: it surfaces the 5 genuine errors above. Any CI that claims green is
> either running a narrower config, a baseline, or stopping at the OTel/Swoole adapters.
> This is the most actionable finding in the review.

---

## 4. Prioritized recommendations

### P0 — make `composer analyse` pass (gate-blocking)
1. **`HttpExecutionContext`** — introduce `HttpExecutionContextInterface` (in
   `Contract/Shared` or `Adapter/Psr/Contract`) and implement it; or, if deliberately a
   concrete DTO, add it to the `RequireInterfaceRule` exemption list *with* a tracked
   follow-up in `docs/qa.md`. Either way the custom rule must stop failing.
2. **`DefaultArtifactRedactor`** — type the flagged iterables:
   `maskKeys(array<string, mixed> $data, list<string> $keys): array{0: array<string, mixed>, 1: bool}`
   (and likewise `maskThrowable`). This both satisfies level max and hardens a security path.

### P1 — close the DIP leak and harden the redactor
3. Treat the `HttpExecutionContextInterface` extraction (P0-1) as a public-contract addition
   and document it; keep the concrete class as the default implementation.
4. Sweep the 15 `shipmonk.deadMethod` notices: confirm the public fluent/API ones are
   intentionally part of the surface (leave), and remove any genuinely dead private helpers.

### P2 — DRY, assembly, and modern-PHP opportunities
5. Standardize domain value-object validation on `ValidatedStringValueBehavior` (remove the
   inline `new XxxValidator()` calls).
6. Introduce **lazy objects** for expensive optional infrastructure (OTel bridge,
   serialization caches, in-memory stores) per AGENTS.md.
7. Consider extracting `RunItBuilder` / `DefaultExecutionRunner` assembly into subsystem
   factories (publisher stack, context storage, recorder set) to shrink the 24-collaborator
   runner constructor over time.
8. Adopt **property hooks** for value-object computed projections where they replace getter
   boilerplate.

### P3 — polish
9. Centralize the default `DefaultContextRedactionPolicy` instantiation.
10. Add a measured-complexity guard (e.g. cognitive complexity) once a stable threshold and
    baseline exist, rather than relying on convention alone.
11. Provide named builders/factories for the wide-constructor value objects
    (`CloudEvent`, `Measurement`, `SecurityLimits`, `Observation`, `Timeout`) for DX.
12. Verify the recorder `$clock` `property.onlyWritten` reports are PHPStan trait false
    positives (they are read by `InstantClockBehavior::now()`) and, if so, document the
    limitation rather than "fixing" by removing the dependency.

---

## 5. Blockers & risks

- **`composer analyse` is red** (5 genuine errors). Confirm whether CI runs level max across
  the full tree including `OpenTelemetry`/`Swoole`; if it does, CI is currently failing and
  P0 items are required.
- **Infection/mutation cannot run locally** (Codeception `di` bootstrap failure,
  pre-existing). Mutation coverage of the P0/P1 changes is therefore unverified in this
  environment and should be run in CI.
- **OTel/Swoole adapters are large**; ensure they remain covered by `api:check` (they are,
  via the additive baseline) and by the architectural rules — DepTrac already confirms 0
  violations there.

---

## 6. Assumptions

- Reviewed the **current committed tree** (post Phase 3); prior docs live in `docs/archive/`.
- **No source modified** — this document records findings and recommendations only.
- Gates executed in this environment (PHP 8.5): `cs:check`, `architecture`, `api:check`
  passed; `analyse` (PHPStan level max) failed with the 5 genuine errors above; Infection was
  not executed (Codeception bootstrap limitation, pre-existing).
- Conclusions about structural discipline (final/readonly/interface/else/magic-value posture)
  are backed by **repo-wide `grep` scans** across all `src/`; a representative sample of files
  per layer was read in depth (`FiberContextStorage`, `RunItBuilder`, `DefaultExecutionRunner`,
  `BufferedArtifactPublisher`, `HttpExecutionContext`, `DefaultArtifactRedactor`, plus
  architecture/ADR docs). Not all 378 files were read line-by-line.
