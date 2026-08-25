# Architecture

This page explains how DeriveIt is structured and why. It is the user-facing
overview; the binding design decisions live in the
[ADRs](../architecture/decisions/).

## Layers

DeriveIt is organised into deptrac layers (enforced by `composer architecture`):

```
Entry        DeriveIt (public facade) + DeriveItFactory (composition root)
  │
Cli          DeriveCommand, ArgParser, DeriveConsoleSink (--verbose diagnostics)
  │          depends on Entry + Generator + Contract
  ▼
 Generator    InterfaceDeriver, PerTraitInterfaceDeriver,
   │           FamilyDeriver, AdapterGenerator, MethodExtractor,
   │           MethodSignatureFactory, TypeSubstitutor, DeriveConfig,
   │           DerivationCore, DeriveEventEmitter, filters
  │
Resolver     RecursiveClassResolver, RecursiveInterfaceResolver,
  │           RecursiveTraitResolver
  │
Reflection    ClassReflection (+ reflection caches)
  │
Telemetry     NullDeriveEventSink (default no-op sink)
  │
Contract      *ResolverInterface, DeriverInterface,
  │           FamilyDeriverInterface, AdapterGeneratorInterface,
  │           MethodFilterInterface, Telemetry\DeriveEventSinkInterface
  │
Behavior      LazyCreatable (shared utility trait)
```

- **Contract** — interfaces only; depends on nothing. Includes the telemetry
  port `Contract\Telemetry\DeriveEventSinkInterface`.
- **Behavior** — the `LazyCreatable` utility trait (dependency-free); the
  foundational layer that others depend on.
- **Telemetry** — `NullDeriveEventSink`, the default no-op implementation of
  `Contract\Telemetry\DeriveEventSinkInterface`. Derivations emit activities,
  measurements, observations, and occurrences through this optional port
  (facts only — no timing or identity); wiring a real sink is opt-in via the
  factory/facade, and is how derivations attach to a [RunIt](../integrations/runit.md)
  execution.
- **Reflection** — `ClassReflection`, a `ReflectionClass` subclass.
- **Resolver** — the three recursive graph walkers; depend on Contract + Behavior.
- **Generator** — the derivers and helpers; depend on everything below.
- **Cli** — `DeriveCommand` (CLI driver), `ArgParser` (declarative argv parser),
  and `DeriveCliInput`; depends on Entry + Generator + Contract.
- **Entry** — `DeriveIt`, the single public facade, and `DeriveItFactory`, the
  composition root; depends on Generator (+ Contract).

`deptrac` runs with **0 violations**: no upward or lateral leakage between
layers.

## Data flow (flat derivation)

`◄─ telemetry` marks the points where facts are emitted to the optional event
sink; with the default `NullDeriveEventSink` every one of them is a no-op.

```
               ┌─────────────────────────────────────────────┐
 class-string  │                                             │
 ────────────► │  occurred: derivation.started  ◄─ telemetry │
               │        │                                    │
               │        ▼                                    │
               │  MethodExtractor::extract()                 │
               │   [activity surface.extract]   ◄─ telemetry │
               │        │ measured: methods.derived/dropped  │
               │        ▼  filter pipeline (DeriveConfig)    │
               │  MethodSignatureFactory::fromMethod()       │
               │        │  Nette Factory::fromMethodReflection│
               │        │  + TypeSubstitutor (FQCN rewrite)   │
               │        ▼  Nette Method[]                    │
               │  InterfaceDeriver::derive()                 │
               │   [activity leakage.audit]     ◄─ telemetry │
               │        │ measured: substitutions.applied    │
               │        ▼ assemble InterfaceType             │
               │  render → string                            │
               │   measured: source.bytes       ◄─ telemetry │
               │        │                                    │
               │        ▼                                    │
               │  occurred: derivation.completed ◄─ telemetry│
               └─────────────────────────────────────────────┘
```

In family and per-trait modes the pipeline is preceded by a `graph.walk`
activity (root-class BFS discovery, or the recursive trait walk), followed by a
`family.members` measurement; `diff()` wraps its comparison in a
`diff.compute` activity and closes with a `drift.detected` / `drift.clean`
occurrence.

## Component responsibilities

### Telemetry (`Contract/Telemetry` + `Telemetry`)

`DeriveEventSinkInterface` is the single emission port: five methods
(`activityStarted/Finished`, `measured`, `observed`, `occurred`) carrying
**facts only** — names plus scalar attributes. No timing, no execution
identity, no context storage: those are host concerns, so derivations fail
fast by throwing exactly as before and the *host* decides how failures map to
telemetry. The default implementation is the no-op `NullDeriveEventSink`
(zero-cost); sinks thread through `DeriveItFactory`, the optional trailing
`$eventSink` parameter on the facade's per-trait/adapter/family/result/diff
entries, and `--verbose` on the CLI (`DeriveConsoleSink`, human-readable lines
on stderr).

The vocabulary mirrors RunIt's recorder surface 1:1, which keeps an integration
bridge mechanical; see [integrations/runit.md](../integrations/runit.md) for
the shipped bridge package and CI recipes.

### Graph walking (`Reflection` + `Resolver`)

`ClassReflection` extends `ReflectionClass` and delegates
`getClassesRecursively()`, `getInterfacesRecursively()`, and
`getTraitsRecursively()` to the three resolvers. Each resolver recursively
collects its slice of the graph (parents, interfaces, or traits — the latter
including nested traits) and de-duplicates. Resolvers are injected at construction time: `ClassReflection::create()`
accepts optional resolver instances and falls back to a single shared immutable
default, so resolution is injectable without static global state.

### Extraction & filtering (`Generator`)

`MethodExtractor` reads the **concrete class's** public methods
(`ReflectionClass::getMethods(IS_PUBLIC)`) — PHP already resolves trait/parent
conflicts for you — then runs them through the filter pipeline:

1. `VisibilityFilter` — honours `DeriveConfig::visibilityMask()`.
2. `StaticFilter` — drops static methods unless `includeStatic`.
3. `MagicMethodFilter` — drops `__*` methods unless `includeMagic`.
4. `ConstructorFilter` — drops `__construct`/`__destruct` per config.

Each filter implements `MethodFilterInterface` (`keep(ReflectionMethod): bool`),
so you can compose a custom chain.

### Signature factory (`Generator`)

`MethodSignatureFactory` maps each surviving `ReflectionMethod` through
`Nette\PhpGenerator\Factory::fromMethodReflection()` — the single source of truth
for typing — then forces the result to `abstract` + `public` with no body. Before
assignment it applies the `TypeSubstitutor` (see below).

### Type substitution (`Generator`)

`TypeSubstitutor` is built from `DeriveConfig::typeSubstitutionMap`
(class FQCN ⇒ derived interface FQCN). It returns the mapped FQCN verbatim, so
in-memory `InterfaceType` objects and rendered source agree. See
[ADR-0004](../architecture/decisions/ADR-0004-type-substitution.md).

### Derivers (`Generator`)

- **`InterfaceDeriver`** — flat derivation. Assembles the `InterfaceType`,
  enforces the leakage invariant via `assertSelfContained()`, and renders source.
- **`PerTraitInterfaceDeriver`** — per-trait mode. Builds one `InterfaceType` per
  used trait, then composes the main interface so it `extends` exactly those
  derived trait interfaces (conflict resolution: first trait in
  `getTraitsRecursively()` order owns a shared method name). Enforces
   `assertPerTraitSelfContained()`.
- **`DerivationCore`** (REVIEW.md F-4 / R4-2) — the former `AbstractDeriver` base,
   dissolved into a `final readonly` collaborator composed into both derivers. Owns
   the four cross-mode concerns (reflection memoization, skip/allow filtering,
   substitutor seeding with `self`/`static`/`parent` + explicit map, and the
   signature pipeline) plus the `SourceRenderer` and `DeriveEventEmitter` accessors,
   so `InterfaceDeriver` / `PerTraitInterfaceDeriver` stay pure assembly logic.
- **`FamilyDeriver`** — batch mode. Builds the substitution map
  automatically from the supplied classes, then derives each with that map so
  cross-referencing types point at derived interfaces.
- **`AdapterGenerator`** — emits a delegating `ClassType` adapter (constructor
  injection of the adaptee, forwarding bodies) plus the derived interface in one
  self-contained file.

`InterfaceDeriver` and `PerTraitInterfaceDeriver` implement `DeriverInterface`
(`derive()` → `InterfaceType`, `deriveSource()` → `string`). `FamilyDeriver`
implements `FamilyDeriverInterface` (batch / family derivation) and
`AdapterGenerator` implements `AdapterGeneratorInterface` (delegating adapter
generation). All four are assembled by `DeriveItFactory` (Entry) behind their
interfaces (F2 / R.2), so each deriver is polymorphic and independently
testable.

### Entry (`DeriveIt`)

`DeriveIt` is the only public surface most users need. It is a **static facade
over the injectable derivers** — `DeriveItFactory` (same Entry layer) is the
composition root that wires the four derivers behind their interfaces, optionally
sharing a reflection cache, a Nette `Factory`, and a telemetry event sink across
a run. It wires the generators together and offers `derive()`,
`deriveInterface()`, `derivePerTrait()`, `deriveTraitInterfaces()`,
`deriveFamily()`, `deriveFamilySource()`, `deriveFamilyFromRoot()`,
`deriveFamilyAdapter()`, `deriveAdapter()`, `deriveWithResult()`, `analyze()`,
`deriveMany()`, and `diff()`; the per-trait, adapter, family, result, and
drift-detection entries accept an optional trailing `?DeriveEventSinkInterface`
(the legacy `derive()` / `deriveInterface()` / `analyze()` / `deriveMany()`
convenience signatures do not).

## Design decisions (ADRs)

- [ADR-0001](../architecture/decisions/ADR-0001-nette-factory-typing.md) — use
  `nette/php-generator` `Factory::fromMethodReflection()` for typing.
- [ADR-0002](../architecture/decisions/ADR-0002-per-trait-composition.md) — interface-per-trait
  composition.
- [ADR-0003](../architecture/decisions/ADR-0003-static-method-policy.md) — static-method policy.
- [ADR-0004](../architecture/decisions/ADR-0004-type-substitution.md) — type-substitution
  strategy (FQCN-canonical).

## Known limitations

- `self` / `static` / `parent` return types render as the literal class name
  (Nette resolves them to the concrete class); explicit substitution covers the
  derived-family case.
- In per-trait mode the composed main interface references the derived trait
  interfaces by their global name (rendered with a leading `\`), because the
  trait interfaces carry no namespace. This is functionally valid PHP but not
  pretty; a future revision may namespace them.
- Post-v1 (documented only): OTel-assisted shape discovery; LLM-assisted
  long-tail edge cases.
- The adapter generator does not emit telemetry yet; events cover the
  derivation pipeline and drift detection (`diff()`).
