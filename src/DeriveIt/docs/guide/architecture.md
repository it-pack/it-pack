# Architecture

This page explains how DeriveIt is structured and why. It is the user-facing
overview; the binding design decisions live in the
[ADRs](../architecture/decisions/).

## Layers

DeriveIt is organised into deptrac layers (enforced by `composer architecture`):

```
Entry        DeriveIt (public facade)
  │
Generator    InterfaceDeriver, PerTraitInterfaceDeriver,
  │           FamilyDeriver, AdapterGenerator, MethodExtractor,
  │           MethodSignatureFactory, TypeSubstitutor, DeriveConfig, filters
  │
Resolver     RecursiveClassResolver, RecursiveInterfaceResolver,
  │           RecursiveTraitResolver
  │
Reflection    ClassReflection
  │
Contract      *ResolverInterface, DeriverInterface, MethodFilterInterface
  │
Behavior      LazyCreatable (shared utility trait)
```

- **Contract** — interfaces only; depends on nothing.
- **Behavior** — the `LazyCreatable` utility trait (dependency-free); the
  foundational layer that others depend on.
- **Reflection** — `ClassReflection`, a `ReflectionClass` subclass.
- **Resolver** — the three recursive graph walkers; depend on Contract + Behavior.
- **Generator** — the derivers and helpers; depend on everything below.
- **Entry** — `DeriveIt`, the single public facade; depends on Generator.

`deptrac` runs with **0 violations**: no upward or lateral leakage between
layers.

## Data flow (flat derivation)

```
              ┌─────────────────────────────────────────────┐
 class-string │                                             │
 ───────────► │  ClassReflection::create($class)            │
              │        │                                     │
              │        ▼                                     │
              │  Recursive*Resolver::resolve()  (graph walk) │
              │        │  returns ReflectionClass[]          │
              │        ▼                                     │
              │  MethodExtractor::extract()                 │
              │        │  filter pipeline (DeriveConfig)     │
              │        ▼  ReflectionMethod[]                 │
              │  MethodSignatureFactory::fromMethod()        │
              │        │  Nette Factory::fromMethodReflection│
              │        │  + TypeSubstitutor (FQCN rewrite)   │
              │        │  + force abstract/public, no body   │
              │        ▼  Nette Method[]                     │
              │  InterfaceDeriver::derive()                  │
              │        │  assemble InterfaceType             │
              │        │  assertSelfContained()  ◄─ leakage  │
              │        ▼                                     │
              │  render → string (PhpNamespace → PhpFile)    │
              └─────────────────────────────────────────────┘
```

## Component responsibilities

### Graph walking (`Reflection` + `Resolver`)

`ClassReflection` extends `ReflectionClass` and delegates
`getClassesRecursively()`, `getInterfacesRecursively()`, and
`getTraitsRecursively()` to the three resolvers. Each resolver recursively
collects its slice of the graph (parents, interfaces, or traits — the latter
including nested traits) and de-duplicates. Resolvers are swappable through
`ClassReflection::setRecursive*Resolver()`.

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
- **`FamilyDeriver`** — batch mode. Builds the substitution map
  automatically from the supplied classes, then derives each with that map so
  cross-referencing types point at derived interfaces.
- **`AdapterGenerator`** — emits a delegating `ClassType` adapter (constructor
  injection of the adaptee, forwarding bodies) plus the derived interface in one
  self-contained file.

All three derivers implement `DeriverInterface` (`derive()` → `InterfaceType`,
`deriveSource()` → `string`).

### Entry (`DeriveIt`)

`DeriveIt` is the only public surface most users need. It wires the generators
together and offers `derive()`, `deriveInterface()`, `derivePerTrait()`,
`deriveTraitInterfaces()`, `deriveFamily()`, and `deriveAdapter()`.

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
