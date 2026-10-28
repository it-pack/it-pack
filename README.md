# it-pack — Execution is the root domain

> A family of PHP libraries that model **execution itself**, so observability,
> configuration, derivation, and more interoperate through a shared vocabulary
> instead of coupling to any single vendor.

## What is it-pack?

it-pack is a monorepo of loosely-coupled, framework-neutral PHP packages.
The gravitational center is **RunIt**, which models execution — Context,
Activity, Measurement, Observation, Occurrence, Snapshot — and then lets
other packages build on that model:

- **ConfigureIt** — configuration as data: load, validate, merge, resolve,
  and serve immutable snapshots through a pluggable pipeline.
- **AsyncIt** — portable, capability-driven async scheduling that implements
  RunIt's `ExecutionEngineInterface`.
- **DeriveIt** — derives clean, self-contained interfaces and adapters from
  legacy/third-party class hierarchies (zero leakage of the adaptee's parents,
  traits, or interfaces).
- **HashIt** — hexagonal hashing (hashes, HMAC, streams, bulk) behind owned ports.
- **CommandIt** — CQRS-style command definition and dispatch with a RunIt-aligned
  lifecycle.
- **DisenchantIt** — finds magic scalar values in PHP source and promotes them
  to named constants/enums.
- **CommitIt** — vendor-neutral VCS API (git first).

Plus bridge packages that wire a consumer package onto the RunIt execution
model: `ConfigureItRunit`, `DeriveItRunit`, `HashItAsync`, `CommitItRunit`, `RunItThirdPartySample`.

Full details: see [docs/REPORT.md](./docs/REPORT.md) for the architecture
review and [docs/EXECUTION.md](./docs/EXECUTION.md) for the execution-model
manifesto.

## Quick start

```bash
# Run the full QA suite across all packages
make quality

# Or run a single lane
make cs-check    # coding style
make analyse     # PHPStan
make test        # Codeception
make architecture # phparkitect

# Run quality checks for a single package
cd src/RunIt && composer quality
```

## Monorepo layout

```text
it-pack/
├── composer.json          # Root (scripts-only) — delegates to make
├── Makefile               # `make quality` runs composer quality per package
├── AGENTS.md              # Repo-wide instructions for AI agents
├── docs/                  # REPORT.md, EXECUTION.md, CONSOLIDATION_PLAN.md, ADRs
├── tools/qa/              # Shared QA templates (phparkitect, phpstan, cs-fixer, infection, codeception)
├── .github/workflows/     # CI: quality.yml matrix (14 packages × PHP 8.4/8.5)├── src/
│   ├── RunIt/             # Execution model & runtime backbone (vocabulary donor)
│   ├── ConfigureIt/       # Configuration SDK + resolution engine (hub)
│   ├   ├── ConfigureItPsr11/   # PSR-11 container integration
│   ├   ├── ConfigureItSymfony/ # Symfony bundle + DI extension
│   ├   ├── ConfigureItSchemaDocs/ # JSON-Schema + Markdown docs
│   ├   ├── ConfigureItTesting/   # Test doubles + assertions
│   ├   └── ConfigureItExplorer/  # Read-only web explorer + graph query
│   ├── AsyncIt/           # Portable async scheduling (canonical ExecutionEngineInterface impl)
│   ├── DeriveIt/          # Interface/adapter derivation from class hierarchies
│   ├   ├── ConfigureItRunit/     # Bridge: ConfigureIt → RunIt
│   ├   ├── DeriveItRunit/        # Bridge: DeriveIt → RunIt
│   ├   ├── CommitItRunit/        # Bridge: CommitIt → RunIt (observer adapter)
│   ├   ├── HashItAsync/          # Bridge: HashIt → AsyncIt
│   ├   └── RunItThirdPartySample/ # Third-party conformance reference
│   ├── HashIt/            # Hexagonal hashing utility
│   ├── CommandIt/         # CQRS command dispatch
│   ├── DisenchantIt/      # Magic scalar → constant/enum promotion
│   ├── CommitIt/          # Vendor-neutral VCS API
│   ├── LazeIt/            # PHP native lazy objects SDK
│   ├── FileIt/            # File metadata value objects
│   └── ExtendIt/          # Experimental class inspection toolkit
└── examples/ lab/ var/    # Tinkering / samples (not shipped)
```

## Tooling standard

Every package with source code follows one stack:

| Concern | Tool |
|---|---|
| Tests | **Codeception** |
| Architecture | **phparkitect** (deptrac configs archived) |
| Dev baseline | **just-bases/\*** |
| Static analysis | **PHPStan** (level: max) |
| Coding style | **PHP-CS-Fixer** |
| Mutation testing | **Infection** |

Each package exposes the [QA lane contract](./AGENTS.md#qa-lane-contract):
`test`, `test:unit`, `cs:check`, `cs:fix`, `analyse`, `architecture`, `mutation`,
`quality`, `security:audit`. Run `composer quality` in any package to execute
the full gate.

## Architectural doctrine

- **Hexagonal (ports & adapters)**: `Contract/` (ports) → `Adapter/` (technology)
  → `Core/Domain/Application/SDK` (business logic).
- **Ports, not imports**: a package's Core must not import `ItPack\RunIt\*`.
  RunIt is wired in through a dedicated bridge package instead. Enforced via
  `phparkitect` (`forbidSibling()` rules).
- **Execution vocabulary**: RunIt's Execution → Context, Activity, Measurement,
  Observation, Occurrence, Snapshot is the shared lingua franca. External
  standards (OpenTelemetry, CloudEvents, PSR) are adapters, not the model.

See [docs/EXECUTION.md](./docs/EXECUTION.md) and
[docs/REPORT.md](./docs/REPORT.md) for the full design.

## Contributing

Read [AGENTS.md](./AGENTS.md) and [CONTRIBUTING](./AGENTS.md#workflow-discipline)
first. Each package also has a local `AGENTS.md` that overrides repo-level
instructions for that package's internals.

Key rules:

1. Keep changes small and focused; one coherent change at a time.
2. Run `composer quality` in the affected package before pushing.
3. Never disable architecture rules or regenerate baselines to make CI pass.
4. Do not commit generated artifacts unless the repo requires them.
