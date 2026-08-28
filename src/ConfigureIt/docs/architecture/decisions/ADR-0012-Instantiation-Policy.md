# ADR-0012 — Instantiation policy & the value-object carve-out

- **Status:** Accepted (2026-08-26, Milestone 6 / NV10)
- **Supersedes:** informal "default-construct collaborators in constructors" habit.
- **Related:** ADR-0011 (rewriter decomposition, which first removed a concrete
  `abstract class` default), ADR-0008 (fibers — rejected; deterministic
  resolution), AGENTS.md "Composition over Inheritance".

## Context

Two directional wrinkles remained after Milestone 5 (NV9) and the earlier
security work (NV10): the fluent {@see \ItPack\ConfigureIt\Core\ConfigurationBuilder}
imported the composition root (`DefaultConfigurationWiring`) to supply a default
wiring, and six core/adapters services default-constructed their injectable
collaborators inside their own constructors
({@see \ItPack\ConfigureIt\Core\ConfigurationResolver},
{@see \ItPack\ConfigureIt\Core\IncludeProcessor},
{@see \ItPack\ConfigureIt\Adapter\NativeConfigurationSchema},
{@see \ItPack\ConfigureIt\Core\ConfigurationDiffer},
{@see \ItPack\ConfigureIt\Core\ConfigurationDriftDetector},
{@see \ItPack\ConfigureIt\Cache\FileSystemCache}).

REVIEW NV10 (and the carried-over R10) flagged both: a service that
`new`s another service inside its constructor hides the composition root,
defeats the "no service is built inside another service" rule, and makes the
dependency graph unverifiable by deptrac. The plan's Milestone 6 converted
these into explicit, verified gates.

## Decision

ConfigureIt adopts an explicit **instantiation policy**:

1. **Injectable collaborators are supplied by the composition root.** Any
   service, factory, runner, reporter, or wiring object that *does work* is an
   injectable collaborator. Its dependencies arrive through the constructor
   from `Wiring\DefaultConfigurationWiring` (or `Wiring\Configurations::builder()`),
   never via a `= new X()` default in another service's constructor.
2. **Value objects may be constructed inline.** Immutable domain data
   (`ConfigurationNode`, `Provenance`, `ValidationMessage`, `Observation`,
   `TypedSnapshot` wrappers, graph instances, and the scalar/secret
   vocabularies) *are* data, not services. Constructing them with `new` at a
   call site is correct and explicitly carved out.
3. **The composition root is the only place that `new`s Adapter/Bridge
   concretes.** Core depends only on `Contract/` + `ValueObject/`; it never
   references `Wiring/`, `Adapter/`, or `Bridge/`. The wiring layer lives
   outside Core and is the sole `new`-er of concrete collaborators.
4. **The builder no longer imports the composition root.** `ConfigurationBuilder`
   takes an optional `?ConfigurationWiringInterface $wiring = null`; a null
   wiring is a programming error surfaced loudly at `build()` time. The supported
   entry point is `Wiring\Configurations::builder()`, which injects
   `DefaultConfigurationWiring` + `ConfigurationFactory`.

### Mechanical enforcement

- The six inventory classes now require their collaborators as constructor
  parameters (no `= new` defaults). `src/Wiring/DefaultConfigurationWiring`
  satisfies them.
- `tests/Unit/Wiring/NoInjectableConstructorDefaultsTest` is a tokenizer guard
  (mirroring `NoAbstractClassesTest`) that fails the build if a `= new`
  reappears in a constructor parameter default of any inventory file.
- `deptrac` is tightened (see `deptrac.yaml`) so `Contract → Core|Adapter|
  Bridge|Wiring` and `Core → Adapter|Bridge|Wiring` are hard violations, making
  a Core→Wiring import (or any upward edge) impossible to reintroduce silently.

## Consequences

- **Positive:** the dependency graph is now exactly the intended layering;
  composition is observable and testable; the builder's imports shrink to
  contracts only; two release-blocking gates (`composer architecture`,
  the constructor-default scan) protect the policy.
- **Neutral:** call sites that previously relied on implicit defaults now pass
  the collaborators. Application code uses `Wiring\Configurations::builder()`,
  so end-user call sites are unchanged. Internal tests were updated to inject
  the now-required dependencies.
- **Negative:** a handful of internal constructors gained required parameters;
  the CLI `DiffCommand` (a blessed boundary) keeps a pragmatic
  `= new ConfigurationDiffer(new RedactingRenderer())` default, exempt from the
  scan by virtue of being outside the inventory scope (documented here).
