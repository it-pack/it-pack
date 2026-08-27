# RunIt Documentation Plan — Reasoning & Suggestions

> **Scope of this document.** This is a *planning and reasoning* artifact, not the
> documentation itself. It inventories the existing RunIt docs, identifies gaps and
> inconsistencies, and proposes an information architecture (IA) plus a curated,
> step-by-step learning path for three reader personas. It also lists concrete new
> documents to create and specific consolidation/polish fixes, referencing real files.
> The actual user guide, tutorial, and index are follow-up work items, sequenced in
> the final section.

- Working tree: `it-pack/run-it` (`src/RunIt`).
- Status context: `1.0.0-rc.1`, public API frozen (372 symbols), BC enforced by
  `tools/api-compat/api-baseline.json`. Docs-only changes never touch the API.

---

## 1. Thesis

RunIt already has *a lot* of documentation, but it is **topic-siloed and
reference-heavy**. A newcomer opens `README.md` (1,463 lines) and is immediately
dropped into a full concept dump, or wanders into `docs/ROADMAP.md` (3,400+ lines of
milestone history) and `docs/adr/` (24 decision records). There is no single
**navigation hub**, no **guided learning path**, and no clear **"start here"** for any
of the three distinct audiences (evaluator, integrator, extender).

The goal of this plan is to make RunIt *intuitive to learn* by applying two
principles:

1. **Progressive disclosure** — give a 5-minute "hello world" first, then layer
   concepts, architecture, recipes, and deep dives behind links.
2. **Single source of truth** — stop duplicating the same material across README,
   `docs/concepts/*`, `docs/architecture/*`, and `docs/QA.md`. Keep one canonical
   home per topic and cross-link.

The deliverable is a **documentation front door** (`docs/index.md`), a **getting
started** guide, a **progressive tutorial**, and a **consolidated README** that stops
re-telling everything.

---

## 2. Current documentation inventory & assessment

### 2.1 What already exists (strengths)

| Area | Files | Assessment |
|---|---|---|
| Project front matter | `README.md` | Comprehensive but *too* large; doubles as reference + guide. |
| Execution philosophy | `docs/EXECUTION.md` | Excellent narrative model and "why". Keep as the conceptual anchor. |
| Concept reference | `docs/concepts/*.md` (15 files) | Strong, canonical per-artifact reference. **This should be the source of truth** for concepts. |
| Architecture | `docs/architecture/*.md` (9 files) | Good; `hexagonal-architecture.md` and `dependency-graph.md` have **stale references** (see §3). |
| Integrations | `docs/integrations/*.md` (8 files) + `README.md` | Solid, adapter-by-adapter. |
| Recipes | `docs/recipes/*.md` (15 files) | Task-oriented and practical. Assume model knowledge. |
| ADRs | `docs/adr/ADR-*.md` (24 files) | Deep rationale; intimidating without an index. |
| Releases / compat | `docs/releases/1.0.0-rc.1.md`, `docs/compatibility.md`, `docs/UPGRADING.md`, `docs/conformance.md` | Good. |
| QA / security / benchmarks | `docs/QA.md`, `docs/SECURITY.md`, `docs/security/*`, `docs/benchmarks.md` | Present; some overlap with README + AGENTS. |
| Runnable examples | `examples/*.php` (18 files) + `examples/conformance/*` | **Underused**: not surfaced as a learning tour. |
| Vocabulary | `vocab/run-it.ttl`, `vocab/run-it-taxonomy.ttl` | Linked from concepts but never consolidated into a glossary. |

### 2.2 Key weaknesses

1. **No navigation hub.** There is no `docs/index.md` / `docs/README.md`. Readers
   must guess the path from the giant README TOC (`README.md:34`) or the
   `docs/integrations/README.md` "Guides" list.
2. **README duplicates `docs/concepts/*` and `docs/architecture/*`.** README
   "Core Concepts" (`README.md:183-316`) re-tells Execution/Context/Activity/
   Measurement/Observation/Occurrence/Snapshot that already live in
   `docs/concepts/*.md`. README "Architecture" (`README.md:944-1051`), "Security and
   Privacy" (`README.md:1162-1208`), "Performance" (`README.md:1209-1239`), and
   "Testing" (`README.md:1240-1275`) overlap with `docs/architecture/*` and
   `docs/QA.md`.
3. **Stale references contradict the frozen RC state.** `README.md` was just fixed to
   remove `Adapter/Callback` and `SDK/Middleware`, but:
   - `docs/architecture/hexagonal-architecture.md:84` still lists
     `src/Adapter/{Null,Memory,Callback,Fiber,Common}` (Callback was removed).
   - `docs/architecture/hexagonal-architecture.md:92` still says "SDK offers
     builders, decorators, **middleware**, and attributes".
   - `docs/architecture/dependency-graph.md` is a *generated* file that may still
     reference removed nodes — must be regenerated (`composer architecture`).
   - `docs/releases/1.0.0-rc.1.md` and `docs/ROADMAP.md` mention Callback/Middleware
     in *removal* notes (correct), but the live architecture doc is wrong.
4. **No guided tutorial.** `docs/recipes/*` are great *reference recipes* but assume
   you already understand the model. There is no "build up from hello-world" walkthrough.
5. **No use-case / decision guidance.** Nothing answers "When should I use RunIt?",
   "OTel vs PSR-3 vs CloudEvents — which do I need?", or "RunIt vs a logging/APM lib".
6. **No ecosystem page.** Sibling packages (`it-pack/run-it-third-party-sample`,
   ConfigureIt) and the future bridge-package strategy (M16) are scattered across
   `docs/THIRD_PARTY_PLAN.md`, `docs/ROADMAP.md`, and AGENTS. A reader cannot see the
   whole ecosystem at a glance.
7. **Examples are orphaned.** `examples/*.php` (e.g. `sdk.php`, `nested-activities.php`,
   `successful-execution.php`) are runnable but never linked from the docs as a tour.
8. **Inconsistent vocabulary in navigation.** README uses "Non-Intrusive Adoption"
   (`README.md:654`) with sub-sections "Callable decoration", "Interface decoration",
   "Metadata attributes" — these map to `docs/concepts/attributes.md` and
   `docs/recipes/*` but the naming is not cross-linked.
9. **ADR cliff.** 24 ADRs with no index or "principles summary". New readers either
   ignore them or drown.

---

## 3. Identified gaps & inconsistencies (consolidation targets)

| # | Issue | File(s) | Fix |
|---|---|---|---|
| G1 | No docs navigation hub | (missing `docs/index.md`) | Create `docs/index.md`. |
| G2 | README duplicates concept + architecture + QA docs | `README.md:183-316`, `944-1275` | Slim README to summaries + links; keep `docs/concepts/*` canonical. |
| G3 | Stale `Callback` adapter reference | `docs/architecture/hexagonal-architecture.md:84` | Remove `Callback` from common-adapter list. |
| G4 | Stale `SDK middleware` reference | `docs/architecture/hexagonal-architecture.md:92` | Change to "builders, decorators, attributes". |
| G5 | Generated dependency graph may be stale | `docs/architecture/dependency-graph.md` | Regenerate via `composer architecture` after RC tree is final. |
| G6 | `docs/architecture/coding-standards.md` duplicates AGENTS | `docs/architecture/coding-standards.md` | Replace body with a short pointer to `AGENTS.md` + the few RunIt-specific notes. |
| G7 | No guided tutorial | (missing) | Create `docs/getting-started.md` + optional `docs/tutorial/*`. |
| G8 | No use-case / decision guide | (missing) | Create `docs/use-cases.md`. |
| G9 | No ecosystem page | (missing) | Create `docs/ecosystem.md`. |
| G10 | Examples not surfaced | `examples/*.php` | Link from getting-started/tutorial and `docs/index.md`. |
| G11 | No glossary / vocab consolidation | (missing) | Create `docs/glossary.md` linking `vocab/run-it.ttl`. |
| G12 | ADR cliff | `docs/adr/*` | Add `docs/adr/README.md` index + a "Principles" summary in getting-started. |
| G13 | README "Quick Start" buried | `README.md:318` | Keep, but make `docs/getting-started.md` the canonical quick path and link it. |

---

## 4. Proposed information architecture (sitemap)

The target structure keeps all existing good content but adds a **front door** and a
**learning track**, and makes README a *curated index* rather than a copy of everything.

```text
README.md                      # CURATED: what/why/install + links to docs/index.md
docs/
  index.md                     # NEW front door: audience-based entry points + full map
  getting-started.md           # NEW 10-min hello world + "where to go next"
  tutorial/                    # NEW progressive, build-up series (optional but recommended)
    01-hello-execution.md
    02-activities.md
    03-measure-and-observe.md
    04-occurrences-and-snapshots.md
    05-context-and-propagation.md
    06-nesting-and-flow.md
    07-publish-and-integrate.md
  use-cases.md                 # NEW when-to-use + decision guides
  ecosystem.md                 # NEW sibling/future packages
  glossary.md                  # NEW terms + vocab.ttl links
  concepts/                    # CANONICAL reference (keep; minor polish)
  architecture/                # keep; fix G3/G4/G5/G6
  integrations/                # keep; add cross-links to tutorial + use-cases
  recipes/                     # keep; add "prerequisites" pointers to tutorial
  adr/                         # keep; add adr/README.md index (G12)
  releases/  compatibility.md  UPGRADING.md  conformance.md  QA.md  SECURITY.md
  security/  benchmarks.md  EXECUTION.md  ROADMAP.md  dogfooding.md
  archive/                     # keep as historical; exclude from main nav
```

**Principle:** README and `docs/index.md` answer "where do I start?"; `concepts/`,
`architecture/`, `integrations/`, `recipes/`, `adr/` answer "how does X work?" in
depth; `getting-started.md` + `tutorial/` answer "teach me step by step".

---

## 5. The step-by-step learning path (core ask)

Three personas, each a numbered reading/writing path. Rationale: cognitive load drops
when a reader only sees the next 1–2 steps, not a 1,463-line wall.

### Persona A — "I'm evaluating RunIt" (≈15 minutes)

Goal: decide *whether* RunIt fits, without installing.

1. `README.md` → "Purpose" (`README.md:64`) + "Project Status" (`README.md:118`)
   → understand what it is and that the API is frozen/stable.
2. `docs/EXECUTION.md` → "Philosophy" + "Execution Model" sections → the mental model
   ("Everything happens within an execution").
3. `docs/index.md` → "For evaluators" box → one-paragraph architecture summary.
4. `docs/use-cases.md` → "When to use RunIt" + "RunIt vs a logging/APM library"
   decision guide → fit check.
5. `docs/ecosystem.md` → see sibling packages and maturity.
6. *Decision point:* if it fits, jump to Persona B.

### Persona B — "I'm integrating RunIt into my app" (hands-on, ≈1–2 hours)

Goal: ship a working integration.

1. `docs/getting-started.md` → install (`composer require it-pack/run-it:^1.0.0@RC`),
   5-minute hello-world using `examples/sdk.php` and `examples/successful-execution.php`.
2. `docs/tutorial/01-hello-execution.md` → run a callable, read the returned result,
   see lifecycle artifacts.
3. `docs/tutorial/02-activities.md` → bound units of work (maps to
   `docs/recipes/nested-activity.md` and `examples/nested-activities.php`).
4. `docs/tutorial/03-measure-and-observe.md` → measurements + observations
   (`examples/measurements.php`, `examples/observations.php`).
5. `docs/tutorial/04-occurrences-and-snapshots.md` → occurrences + immutable snapshots
   (`examples/occurrences.php`, `examples/snapshots.php`).
6. `docs/tutorial/05-context-and-propagation.md` → immutable context, classifications,
   explicit vs ambient (`examples/context-classification.php`).
7. `docs/tutorial/06-nesting-and-flow.md` → nested executions/activities, error
   handling, `examples/failed-execution.php`.
8. `docs/tutorial/07-publish-and-integrate.md` → pick an adapter from
   `docs/integrations/README.md`; for most apps start with
   `docs/integrations/psr.md` (PSR-3/PSR-14) or `docs/integrations/opentelemetry.md`.
9. `docs/recipes/*` → copy the recipe matching your stack (HTTP, CLI, queue, worker,
   scheduler, workflow).
10. `docs/concepts/*` → deep reference *on demand* as questions arise.

### Persona C — "I'm building an adapter / third-party library" (extension, ≈half day)

Goal: implement a port or consume RunIt without coupling to internals.

1. `README.md` → "Architecture" + "Dependency direction" (`README.md:944-990`) and
   `docs/architecture/hexagonal-architecture.md` → the port boundary.
2. `docs/architecture/dependency-graph.md` → enforced DepTrac edges (regenerate first, G5).
3. `docs/concepts/base-contracts.md`, `docs/concepts/semantic-model.md` → artifact
   contracts every adapter must respect.
4. `docs/concepts/identifiers.md`, `docs/concepts/names-and-kinds.md`,
   `docs/concepts/throwable-normalization.md` → the stable extension points.
5. `docs/integrations/adapter-failure-policies.md` + `docs/architecture/publication.md`
   → how to publish safely (fails-open, recursion guard).
6. `docs/recipes/third-party-adapter.md` + `examples/conformance/third-party-adapter.php`
   → concrete conformance walkthrough.
7. `docs/conformance.md` + `examples/conformance/run-conformance.php` → run the
   test-framework-agnostic suite.
8. `docs/adr/README.md` (new index) → read ADR-006 (external standards are adapters),
   ADR-010 (core has no mandatory integration), ADR-016 (bridge package split),
   ADR-020/021/022 (bridge placement) before publishing.
9. `docs/THIRD_PARTY_PLAN.md` → usability expectations and the sample package.

---

## 6. Concrete new documents to create

### 6.1 `docs/index.md` — the front door
- One-paragraph "What is RunIt".
- Three audience boxes (evaluator / integrator / extender) linking to the paths in §5.
- A compact full-map (the sitemap from §4) with one-line descriptions.
- "New here?" → `getting-started.md`. "I want the model" → `EXECUTION.md` +
  `concepts/`. "I want architecture" → `architecture/`. "I have a task" → `recipes/`.
- Link `examples/` as "runnable tour".

### 6.2 `docs/getting-started.md` — 10-minute on-ramp
- Install with the RC constraint; note `^1.0.0` after tag.
- Minimal hello-world using `RunItBuilder` (mirror `examples/sdk.php`).
- "What just happened?" — 6 lifecycle steps (from `README.md:344-353`).
- "Where to go next" → the three persona paths + tutorial link.
- Link to `docs/use-cases.md` for fit and `docs/glossary.md` for terms.

### 6.3 `docs/tutorial/*` — progressive build-up
Each file: short goal, copy-paste code (use `examples/*.php` as the executable source
of truth), "what you learned", "next". Designed to be read in order; each builds on the
previous. The seven-part split in §5 mirrors the domain model order
(Execution → Activity → Measurement/Observation → Occurrence/Snapshot → Context →
Nesting → Publication), which matches how the model is introduced in
`docs/concepts/semantic-model.md`.

### 6.4 `docs/use-cases.md` — when-to-use + decisions
- "RunIt vs a logging library / APM / tracing SDK" comparison table.
- "Pick your integration" decision tree: need logs? → PSR-3; need tracing? → OTel;
  need event streaming? → CloudEvents; need HTTP context? → PSR HTTP; high concurrency?
  → Swoole/Fibers. Link each leaf to `docs/integrations/*` and `docs/recipes/*`.
- Common deployment shapes (CLI, HTTP, queue, worker, scheduler, workflow) → recipe links.

### 6.5 `docs/ecosystem.md` — the bigger picture
- `it-pack/run-it` (this package) — core + SDK + first-party adapters.
- `it-pack/run-it-third-party-sample` — validated third-party usability.
- ConfigureIt — the reference consumer (`docs/EXECUTION.md` "ConfigureIt Example").
- Future bridge packages per `docs/adr/ADR-016` and `docs/ROADMAP.md` M16.
- Vocab packages (`vocab/run-it.ttl`, `vocab/run-it-taxonomy.ttl`).

### 6.6 `docs/glossary.md` — consolidate terminology
- One term per line: Execution, Activity, Measurement, Observation, Occurrence,
  Snapshot, Context, Kind, Name, Sequence, Normalization, Port, Adapter, Ambient
  context, Publication pipeline.
- Each links to its `docs/concepts/*.md` home and, where one exists, its
  `vocab/run-it.ttl` IRI (per AGENTS "Vocabulary Alignment").

### 6.7 `docs/adr/README.md` — ADR index (G12)
- Grouped by theme (domain, architecture, security, integrations, evolution).
- Each ADR one line: number, title, one-sentence "why it matters".
- A "Principles every reader should know" section summarizing the 5–6 load-bearing ADRs
  (001 root aggregate, 002 immutability, 006 external=adapters, 008 business exceptions,
  012 deny-by-default secrets, 014 recursion guards).

---

## 7. Consolidation & polish actions (specific)

1. **Slim `README.md` (G2).** Keep: Purpose, Status, Requirements, Installation,
   a *short* Core Concepts summary (2–3 lines per concept linking to
   `docs/concepts/*.md`), Quick Start, a short Architecture pointer, Security/Perf
   one-liners, and the full "Development / Contributing / Versioning" sections. Move the
   deep concept/architecture/QA prose to the canonical `docs/*` files and replace with
   links. This cuts ~600–900 lines and removes duplication.
2. **Fix `docs/architecture/hexagonal-architecture.md` (G3, G4).** Remove `Callback`
   from the common-adapter list (`:84`) and change "builders, decorators, **middleware**,
   and attributes" (`:92`) to "builders, decorators, and attributes". Align with the
   already-correct README.
3. **Regenerate `docs/architecture/dependency-graph.md` (G5)** with
   `composer architecture` once the RC tree is final, so it reflects removed dirs.
4. **Dedupe `docs/architecture/coding-standards.md` (G6)** to a short summary +
   "see `AGENTS.md` for the authoritative standards".
5. **Cross-link terminology (G8/G11).** Add a "Related" footer to every `docs/concepts/*.md`
   pointing to the glossary, the matching recipe, and the matching integration.
6. **Surface `examples/` (G10).** Add an "Examples" section to `docs/index.md` and a
   "Run this" link at the top of each tutorial/recipe that has a matching example file.
7. **Unify vocabulary in navigation (G8).** In README "Non-Intrusive Adoption"
   (`README.md:654`), link each sub-section to `docs/concepts/attributes.md` and the
   relevant recipe, so the three adoption styles are discoverable from one place.
8. **Exclude `docs/archive/` from the main nav** (it is historical `REVIEW_*` /
   `REFACTORING_PLAN_*` material) to avoid overwhelming new readers; keep it reachable
   from `docs/index.md` only as "historical".

---

## 8. Discoverability & tooling note

No build tooling (MkDocs/Docusaurus) is required or proposed — a plain
`docs/index.md` markdown hub works with GitHub/any renderer and avoids adding a
framework dependency (consistent with ADR-010 "core has no mandatory integration").
If the ecosystem later wants a published site, `docs/index.md` is the natural MkDocs
`index.md` and the sitemap in §4 is the `nav`. This keeps the option open without
committing to tooling now.

---

## 9. Sequenced implementation plan

| Phase | Work | Risk | Depends on |
|---|---|---|---|
| **P0 — Unblock** | Fix G3/G4/G5/G6 (stale arch refs, regenerate graph, dedupe coding-standards). | Low; docs-only. | RC tree final. |
| **P1 — Front door** | Create `docs/index.md`, `docs/glossary.md`, `docs/adr/README.md`. | Low. | P0. |
| **P2 — On-ramp** | Create `docs/getting-started.md` + `docs/tutorial/*` (7 parts) using `examples/*.php`. | Low–med (content volume). | P1. |
| **P3 — Slim README** | Refactor `README.md` per §7.1; cross-link to `docs/*`. | Med (large edit); keep `api:check`/BC unaffected. | P1, P2. |
| **P4 — Fit & ecosystem** | Create `docs/use-cases.md`, `docs/ecosystem.md`; link recipes↔integrations↔tutorial. | Low. | P1, P2. |
| **P5 — Verify** | `composer validate`, `composer cs:check` (none affected), link-check the new docs, regenerate dependency graph. | Low. | All. |

Recommended order for a single focused pass: **P0 → P1 → P2 → P4 → P3 → P5**, doing
README slim-last so the canonical `docs/*` pages exist before README points at them.

---

## 10. Open questions for the maintainer

1. **Tutorial depth:** full `docs/tutorial/*` 7-part series, or a single longer
   `docs/getting-started.md` that links out? (Recommend the series for "intuitive
   step-by-step"; it maps 1:1 to the domain model.) – Answer: full series.
2. **README slimness:** Is removing the in-README deep concept/architecture prose
   acceptable, or should README remain the single comprehensive reference and
   `docs/index.md` merely point into it? (This plan assumes README becomes curated;
   confirm before P3.) – Answer: README should be slimmed.
3. **Examples as tests:** should `examples/*.php` be wired into CI as runnable smoke
   tests so the tutorial "Run this" links never rot? (Recommended, low cost.) – Answer: yes.
4. **Published site:** defer all static-site tooling until after 1.0.0 (M16), keeping
   markdown-only for now? — Answer: yes.

---

## 11. References (existing files this plan builds on)

- `README.md` (entry; to be slimmed per §7.1)
- `docs/EXECUTION.md` (conceptual anchor)
- `docs/concepts/semantic-model.md`, `docs/concepts/execution.md`, `docs/concepts/*`
- `docs/architecture/hexagonal-architecture.md` (fix G3/G4), `docs/architecture/dependency-graph.md` (regen G5), `docs/architecture/coding-standards.md` (dedupe G6)
- `docs/integrations/README.md`, `docs/integrations/adapter-failure-policies.md`, `docs/integrations/{psr,opentelemetry,cloudevents,swoole,fibers,optional-dependencies,compatibility-matrix}.md`
- `docs/recipes/*` (15 files)
- `docs/adr/ADR-*.md` (24; index needed per G12)
- `docs/releases/1.0.0-rc.1.md`, `docs/compatibility.md`, `docs/UPGRADING.md`, `docs/conformance.md`, `docs/QA.md`, `docs/SECURITY.md`
- `docs/ROADMAP.md` (M14 docs, M15 RC, M16 ecosystem), `docs/THIRD_PARTY_PLAN.md`, `docs/dogfooding.md`
- `examples/*.php` (18 runnable files + `examples/conformance/*`)
- `vocab/run-it.ttl`, `vocab/run-it-taxonomy.ttl`
- `AGENTS.md` (authoritative standards; coding-standards doc should point here)
- `tools/api-compat/api-baseline.json` (frozen API context)
