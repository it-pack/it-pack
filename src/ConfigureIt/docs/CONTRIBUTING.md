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
Should this be an adapter?
Can this become a processor?
Is this immutable?
Does this introduce framework coupling?
Is a new interface warranted?
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