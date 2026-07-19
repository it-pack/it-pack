# It-Pack — Code Review Overview

> Scope: in-depth review of the `it-pack` monorepo, focused on **SOLID**, **DRY**, and
> **Hexagonal (ports & adapters)** coherence. Findings are split into three companion
> documents:
>
> - [`FINDINGS_SOLID_DRY.md`](./FINDINGS_SOLID_DRY.md) — single-responsibility, open/closed, Liskov, interface segregation, dependency inversion, and duplication.
> - [`FINDINGS_HEXAGONAL.md`](./FINDINGS_HEXAGONAL.md) — ports/adapters boundaries, dependency direction, composition root.
> - [`FINDINGS_DEFECTS.md`](./FINDINGS_DEFECTS.md) — correctness bugs and broken/dead code that block the above principles from mattering.

## Severity legend

| Icon | Meaning |
|------|---------|
| 🔴   | Defect / broken — does not work as written |
| 🟠   | Architectural smell — will hurt as the code grows |
| 🟡   | Minor / hygiene |

## What this repo actually is

A **monorepo of six sibling mini-packages** under `src/`, each its own PHP namespace:

| Package | Status | Notes |
|---------|--------|-------|
| `DeriveIt` | 🟢 Most mature | README, Codeception tests, phpstan/cs-fixer config, composer deps. The "flagship". |
| `HashIt` | 🟢 Clean / hexagonal | Immutable `Hash` value object; owned ports (`HashContextInterface`, `ContextFactoryInterface`, `FilepathInterface`, `HmacHasherInterface`); native adapters isolated in `src/Adapter/`; `Hasher` + `HashBuilder` composition roots; attribute-driven single-source algorithm catalogue; DX sugar (`Hash::equals`/`Hasher::verify`, immutable `HashBuilder`, `hashStream`, `hashFiles`/`hashDirectory`, HMAC hasher gated to `#[HmacSafe]`); full Codeception + PHPStan + CS gate (37 tests / 168 assertions, 0 phpstan). See `src/HashIt/README.md` and `src/HashIt/docs/ROADMAP.md`. |
| `ExtendIt` | 🟠 Stub | Single empty `ClassInspector`; heavy vendored deps (dagger, roave better-reflection…). |
| `FileIt` | 🟡 Anemic | One mutable data struct (`FileInfo`). |
| `CodeIt` | ⚪ Empty | No source. |
| `ShowCaseIt` | ⚪ Empty | No source. |

> ⚠️ The review below covers the **real** code (`DeriveIt`, `HashIt`, `ExtendIt`, `FileIt`).
> `CodeIt`/`ShowCaseIt` are excluded as they contain no code.
> Vendor trees and `var/` caches exist on disk but are git-ignored, so they are not a
> repo-hygiene problem — though the *per-package in-tree vendor* layout is unusual (see DRY doc).

## Headline conclusions (read these first)

1. **The flagship (`DeriveIt`) is the cleanest and is genuinely close to hexagonal in spirit** — ports (`*ResolverInterface`) are separated from adapters (`*Resolver`), and the README articulates an owned-port philosophy. Its main weakness is that `ClassReflection` doubles as a **global service locator** for those resolvers (static mutable state, defaults hard-wired into the domain object).
2. **`HashIt` was the weakest package** — a non-functional `Hash`, a triple-duplicated algorithm list, a `HashFinalizer` whose constructor parameter was a no-op, and ports that leaked PHP's `HashContext`. It has since been rebuilt into the **reference example of the target architecture** in this monorepo: owned ports, adapters confined to `src/Adapter/`, a `Hasher` composition root, an attribute-driven algorithm catalogue, and a second wave of DX features (timing-safe `Hash::equals`/`Hasher::verify`, an immutable incremental `HashBuilder`, chunked `hashStream`, bulk `hashFiles`/`hashDirectory`, and an HMAC hasher gated to `#[HmacSafe]` algorithms). See `src/HashIt/README.md` and `src/HashIt/docs/ROADMAP.md`.
3. **DRY is the most violated principle overall** — the algorithm catalogue is encoded 3×, the three resolver marker-interfaces add no contract, and the resolver `create()` boilerplate is repeated.
4. **Several packages are incomplete relative to their stated intent** (`HashIt` wiring, `ExtendIt::inspect()` body, the README's "interface generator" which has no class in `src/`).

See the companion files for detail and concrete, file:line-anchored suggestions.
