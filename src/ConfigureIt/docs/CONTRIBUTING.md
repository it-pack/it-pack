Goals

ConfigureIt values maintainability over cleverness.

Every contribution should improve:

readability
composability
testability
extensibility
Principles

Follow:

SOLID
DRY
KISS
YAGNI
Ports & Adapters
Never
expose vendor classes
leak adapter types
hardcode dependencies
mutate snapshots
Prefer
interfaces
value objects
builders
processors
decorators
Pull Requests

Every PR should include:

unit tests
documentation
changelog entry (if applicable)
architectural rationale (if introducing new concepts)
Code Reviews

Reviewers should ask:

Does this belong in Core?
Should itself be an adapter?
Can this become a processor?
Is this immutable?
Does this introduce framework coupling?
Is a new interface warranted?

Quality Gates

Every PR must pass the full gate set (`composer analyse`, `composer cs:check`,
`composer test:unit`, `composer test:integration`, `composer coverage:check`,
`composer architecture`, `composer lint:length`, `composer lint:magic`). Run them all
locally before pushing.

Testing & suites

- **Unit** (`composer test:unit`) — one SUT per test file, collaborators replaced by fakes
  or in-memory doubles. No concrete adapter wiring, no network, no real filesystem beyond
  `vfsStream`/temp dirs.
- **Integration** (`composer test:integration`) — real collaborators (the builder stack,
  concrete readers/loaders, a real OpenTelemetry sink) and the RunIt-style
  **conformance verifiers** (`tests/Integration/Conformance/*VerifierTest`), which are
  parameterized over every `CommandRunnerInterface` / `DecrypterInterface`
  implementation; binary adapters are skipped when their engine is absent.
- When in doubt, a test belongs in **Unit** unless it needs the real composition root or
  cross-component behaviour — then it belongs in **Integration**.

Mutation testing

`composer mutation` runs Infection through `infection/codeception-adapter`, which drives
`codecept run Unit` (Codeception stays the only framework). `composer mutation:changed`
limits the run to git-diff lines for cheap per-PR feedback. MSI floors are provisional at 0
until a CI baseline exists; under the local pcov setup the adapter executes the full Unit
suite per mutant, so a clean full-profile MSI is not measurable locally — read
`var/infection.log` / `var/infection.summary.json` (both gitignored) for the report.

Documentation Philosophy

One addition I'd strongly recommend—because it aligns with the semantic and ontology-driven approach you've been evolving—is to treat the documentation itself as part of the architecture.

Instead of seeing these as isolated Markdown files, think of them as a living knowledge base:

README.md
│
▼
AGENTS.md
│
▼
ARCHITECTURE.md
│
├────────────┐
▼            ▼
PORTS.md       PIPELINE.md
│            │
▼            ▼
GRAPH.md   RESOLUTION.md
│            │
└──────┬─────┘
▼
ROADMAP.md
│
▼
ADR Collection