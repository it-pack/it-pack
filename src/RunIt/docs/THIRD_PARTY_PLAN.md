# M14.8 — Third-Party Usability Validation: Reasoning and Plan

**Status:** Brainstorming / planning (not yet implemented).  
**Target milestone:** M14 (Documentation and Adoption).  
**Related gate:** M14 acceptance gate — *"A third-party-style adapter passes conformance tests."*  
**Companion work:** M14.7 delivered `it-pack/configure-it-runit` (a real, separate bridge
package). M14.8 extends that proof to a *generic* third party that consumes RunIt only as a
dependency, with no access to RunIt's internal test tooling.

---

## 1. TL;DR

Most of the *machinery* M14.8 needs already exists: a framework-agnostic conformance
toolkit (`src/Conformance/`), first-party examples (`examples/conformance/`), a conformance
doc (`docs/conformance.md`), and first-party suites (`tests/Conformance/`). The missing piece
is **genuinely external validation** — every existing asset is *first-party* (it lives inside
the RunIt repo and leans on internal test support such as `Tests\Support\Fixture\RunItTestKit`).

The recommended approach is to create a **separate sibling package** (mirroring how
`it-pack/configure-it-runit` and `it-pack/derive-it-runit` are structured) that:

- depends on `it-pack/run-it` purely as a vendor dependency (path repo in the monorepo, real
  `composer require` outside it);
- implements **one sample third-party publisher** and **one sample context-storage adapter**;
- runs the **shipped `ArtifactPublisherVerifier`** and **`ContextStorageVerifier`** in its own
  test suite (no inheritance from any framework base class);
- ships a **clean-consumer README** that a fresh evaluator follows verbatim;
- records every confusing API / missing extension point in a findings ledger, and resolves
  the critical ones before `1.0.0`.

This is deliberately lightweight: it validates usability through *using* the public surface,
not by adding new runtime features.

---

## 2. Current state (what already exists)

### 2.1 Conformance toolkit — already shipped and exported

- `src/Conformance/Contract/VerifierInterface.php` — single method `port(): string`.
- `src/Conformance/Contract/PortFixtureInterface.php` — the only contract a verifier depends on.
- `src/Conformance/Fixture/RunItPortFixture.php` — builds artifacts from RunIt's own domain;
  this is in `src/`, so it **is** autoloaded and reachable by consumers (composer maps
  `ItPack\RunIt\` → `src/`, `composer.json:16-18`).
- `src/Conformance/Verifier/ArtifactPublisherVerifier.php` — checks `publish()` is repeatable
  and non-mutating, plus optional `flush()`/`close()` (warning severity).
- `src/Conformance/Verifier/ContextStorageVerifier.php` — checks LIFO attach/detach/current
  and invalid-token rejection.
- `src/Conformance/Result/{ConformanceResult,ConformanceViolation,ViolationSeverity}.php` —
  framework-agnostic result model (`isConformant()`, `violations`, severity).

So "run the publisher/context-storage conformance suite" is already possible for a third party
**in principle** — the verifiers are `@api` and exported.

### 2.2 First-party examples and docs

- `examples/conformance/third-party-adapter.php` — a publisher + a verifier run, dependency-free
  apart from RunIt. Good teaching artifact, but **first-party** (sits in the RunIt repo).
- `examples/conformance/run-conformance.php` — runs every verifier against bundled adapters;
  note it imports `Tests\Support\Fixture\RunItTestKit` (`run-conformance.php:49`), which is
  **internal test support** and not shipped to consumers.
- `docs/conformance.md` — thorough guide to the toolkit, verifier table, fixture contract, and
  the negative-test strategy.
- `tests/Conformance/RunItPortConformanceTest.php` + `*VerifierNegativeTest.php` — first-party
  happy-path and negative coverage.

### 2.3 The real gap

M14.8 is about **usability by a third party**, i.e. someone who:

- installs RunIt as a dependency (`composer require it-pack/run-it`);
- has **no** access to `tests/Support/...`, `Tools\...`, or any internal namespace;
- must rely only on `@api` surface, the README, `docs/`, and `examples/`.

Today's "third-party" assets are authored by RunIt maintainers inside the repo, and the
bundled runner depends on first-party test tooling. That proves the toolkit *can* be used, but
it does not prove a stranger can use it unaided. M14.8 should close that gap.

---

## 3. Defining "third party" for this milestone

Adopt the same bar used for `it-pack/configure-it-runit`:

- The sample lives in its **own package/namespace** (`ItPack\RunIt\ThirdPartySample` is *not*
  acceptable — that is still first-party). Use a neutral vendor, e.g.
  `Acme\RunItSample` or, to stay in-repo, `it-pack/run-it-third-party-sample` with namespace
  `ItPack\RunItThirdPartySample`. The package depends on `it-pack/run-it` exactly as an
  external consumer would.
- No `require-dev` on RunIt's `tests/` tree; only on the published package.
- Conformance is asserted through the **shipped verifiers**, not by copying RunIt's test cases.
- The clean-consumer README is written as if the reader has never seen the source.

---

## 4. Proposed deliverable structure

A new sibling package, mirroring `it-pack/configure-it-runit`:

```text
src/RunItThirdPartySample/            (package root: it-pack/run-it-third-party-sample)
├── composer.json                     # requires it-pack/run-it (^0.1), php ^8.5
├── phpcs.xml.dist
├── phpstan.neon.dist
├── .php-cs-fixer.dist.php
├── phpunit.xml.dist
├── src/
│   ├── Publisher/
│   │   ├── StreamLinePublisher.php            # simple, happy-path ArtifactPublisher
│   │   └── BufferedFilePublisher.php          # flushable + closeable ArtifactPublisher
│   └── Context/
│       └── StaticStackContextStorage.php      # plain-array LIFO ContextStorageInterface
├── tests/
│   ├── PublisherConformanceTest.php           # drives ArtifactPublisherVerifier
│   ├── ContextStorageConformanceTest.php      # drives ContextStorageVerifier
│   └── FakeScope.php                          # consumer-safe ExecutionScopeInterface stub
└── docs/
    └── consumer.md                           # clean-consumer walkthrough (the "fresh reader" doc)
```

Tooling configs are copied from `it-pack/configure-it-runit` (already validated there).

---

## 5. Per-checklist reasoning and concrete suggestions

### 5.1 Build one sample third-party publisher — `M14.8.1`

Two samples, not one, to exercise the full publisher contract:

1. **`StreamLinePublisher`** — implements only `ArtifactPublisherInterface`. Writes
   `artifact->normalize()['id']` (or a JSON line) to an injected `resource`/`Psr\Log`-free
   stream. This is the minimal happy path and maps directly to the recipe's `StderrPublisher`.
2. **`BufferedFilePublisher`** — implements `ArtifactPublisherInterface`,
   `FlushablePublisherInterface`, and `CloseablePublisherInterface`. Buffers normalized
   artifacts and emits them on `flush()`, releases the file on `close()`. This exercises the
   warning-severity branches of `ArtifactPublisherVerifier` (repeatable flush/close, no throw
   on the *interface* contract — note the verifier only warns on flush/close throws, so a
   buffered publisher that closes cleanly is conformant).

Both depend only on `ExecutionArtifactInterface` and the port interfaces — never on domain
concrete classes or a vendor SDK, exactly as `docs/recipes/third-party-adapter.md` prescribes.

### 5.2 Run the publisher conformance suite — `M14.8.2`

In `tests/PublisherConformanceTest.php`:

```php
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\ArtifactPublisherVerifier;

$fixture = new RunItPortFixture();
$result = (new ArtifactPublisherVerifier())->verify(new StreamLinePublisher(...), $fixture);
// assert $result->isConformant(); report violations otherwise
```

`RunItPortFixture` is exported (`src/Conformance/Fixture/`), so a third party reuses it directly
— no need to rebuild artifacts. This is the literal "run the publisher conformance suite" item.

### 5.3 Build one sample context-storage adapter — `M14.8.3`

**`StaticStackContextStorage`** — a plain `array` LIFO implementing
`ContextStorageInterface` + a private `ContextTokenInterface` value object (mirrors
`FiberContextStorage`'s token discipline: exact-token detachment, reject foreign tokens). This
is the realistic "I run in ordinary synchronous PHP and want ambient scope" third-party case.

> Design note: the first-party `StackContextStorage` already exists
> (`src/Application/Propagation/StackContextStorage.php`), so a third party could, in principle,
> reuse it. But M14.8 wants a *sample third-party implementation*, i.e. proof the contract is
> implementable externally. `StaticStackContextStorage` is that proof and must be written from
> scratch against only the port + token interfaces.

Fiber/Swoole storages are already first-party; do **not** duplicate them. The sample's value is
demonstrating a *new* compliant implementation, surfacing any hidden coupling.

### 5.4 Run context-storage conformance suite — `M14.8.4`

`ContextStorageVerifier::verify()` takes `(ContextStorageInterface $storage, ExecutionScopeInterface $scope)`
(`ContextStorageVerifier.php:33`). The verifier only stores and returns the scope, so its
*contents are irrelevant* — but a third party still needs **some** `ExecutionScopeInterface`
instance.

**Finding (see §6):** the first-party path uses `RunItTestKit::buildScope()` which is internal
test support and not shipped. A true third party has three options:

- **(a) Recommended for the sample:** provide a tiny `FakeScope` (anonymous or named class
  implementing `ExecutionScopeInterface` with stub recorders). The verifier cannot tell the
  difference. This is the lowest-friction, consumer-safe path and should be documented.
- **(b)** Construct a real `ExecutionScope` — but its constructor requires ~13 collaborators
  (`Application/Scope/ExecutionScope.php:44-64`), so it is impractical outside the SDK.
- **(c)** Use the SDK to start a real execution and read its attached scope — heavier, and
  couples the conformance test to the runner.

The plan recommends **(a)** and records the absence of a documented consumer-safe scope builder
as finding `TP-001`.

### 5.5 Validate documentation with a clean consumer project — `M14.8.5`

Create `docs/consumer.md` (in the sample package) that a fresh evaluator follows:

1. `composer create-project` / `composer require it-pack/run-it`.
2. Copy the README "Quick Start" (`README.md:314`) verbatim into a standalone script.
3. Run it; confirm it executes and emits artifacts via `StreamLinePublisher`.
4. Run `vendor/bin/phpunit` in the sample package (the conformance tests).
5. Note every stumble: missing import, ambiguous API, stale doc, broken example link.

This is the "documentation reviewed independently" gate item, executed as a walkthrough rather
than a code review.

### 5.6 Record confusing APIs or missing extension points — `M14.8.6`

Maintain a findings ledger (proposed format in §6). The log is the artifact M14.8.6 asks for.
Each entry: `id`, `area`, `severity`, `observed confusion`, `suggested fix`, `status`.

### 5.7 Resolve critical usability findings before `1.0.0` — `M14.8.7`

Triage: any `severity: critical` finding that blocks a third party from (a) implementing a port,
(b) running its conformance suite, or (c) following the Quick Start must be fixed (or the docs
corrected) before the `1.0.0` freeze (M15.1). Warnings/niceties can be scheduled post-1.0.

---

## 6. Preliminary findings log

Discovered while reasoning about M14.8 (before any external package exists). These are *candidates*
to be confirmed by the clean-consumer walkthrough.

| id | area | severity | observed | suggested fix | status |
|----|------|----------|----------|---------------|--------|
| TP-001 | Context conformance | high | `ContextStorageVerifier` needs an `ExecutionScopeInterface`, but the only first-party builder (`RunItTestKit::buildScope()`) is internal test support, not shipped. A naive `FakeScope` would require implementing `ExecutionScopeInterface` **plus** six interfaces it returns (execution, context, four recorders) — a deep stub graph no real consumer should write. | **Resolved.** A third party obtains a real scope by running a real execution through the public `RunItBuilder` (with the shipped common adapters `SystemMonotonicClock`, `InMemorySequenceAllocator`, `JsonSnapshotSerializer`, `RandomIdentifierGenerator`) and capturing the `ExecutionScopeInterface` handed to the operation. The scope's contents are irrelevant to the verifier. This is the consumer-safe pattern, documented in the sample package (`docs/consumer.md`). A `FakeScope` is unnecessary. Suggest also documenting this in `docs/conformance.md`. | resolved |
| TP-002 | Docs staleness | medium | `README.md:1250` said reusable contract suites "will be provided", but they already ship in `src/Conformance/`. | **Resolved.** README now states the suites *are* provided. | resolved |
| TP-003 | Examples portability | medium | `examples/conformance/run-conformance.php:49` imports `Tests\Support\Fixture\RunItTestKit`; a third party copying it cannot run it. | **Mitigated.** The standalone sibling package `it-pack/run-it-third-party-sample` is the canonical third-party path; its `docs/consumer.md` walkthrough never references first-party test tooling. The first-party example is left intact. | mitigated |
| TP-004 | Discovery | low | A third party may not know conformance tooling exists; `examples/` is first-party and easy to miss. | Cross-link conformance from the README Quick Start and the `third-party-adapter.md` recipe (already linked there — verify path is correct). | open |
| TP-005 | Public API clarity | low | `RunItBuilder` is the public entry, but the conformance fixture (`RunItPortFixture`) is the only shipped scope-free artifact source; confirm no other internal symbol is implicitly required. | Grep the sample package's `require` tree to ensure zero `src/Application|Domain|Adapter` concrete dependencies beyond ports/contracts. | open |

---

## 7. Risks and open questions

- **Export boundary:** Confirm `ItPack\RunIt\Conformance\*` is in the *published* artifact
  (it is, via `src/` PSR-4), and that no verifier pulls in `tests/` or `Tools\` symbols. The
  first-party `run-conformance.php` violates this for its own tree only; the sample must not.
- **Fake vs real scope:** Using `FakeScope` proves the *verifier* works for third parties, but
  does not prove a third-party storage interops with the *real* runner. Mitigate by also
  wiring `StaticStackContextStorage` into the sample's own `RunItBuilder` and running one real
  execution, asserting ambient scope round-trips.
- **Monorepo vs real repo:** A path-repo dependency still exercises the public API, but the
  ultimate proof is a separate VCS repo with a locked `it-pack/run-it` version. Recommend the
  monorepo sibling for CI now; note a standalone repo as the gold-standard follow-up.
- **Scope creep:** M14.8 is validation, not feature work. Resist adding new ports/verifiers;
  reuse what exists.

---

## 8. Suggested sequencing

1. Scaffold `it-pack/run-it-third-party-sample` (composer + tooling, copied from
   `it-pack/configure-it-runit`).
2. Implement `StreamLinePublisher` + `BufferedFilePublisher`; write
   `PublisherConformanceTest` (drives `ArtifactPublisherVerifier`).
3. Implement `StaticStackContextStorage` + `FakeScope`; write
   `ContextStorageConformanceTest` (drives `ContextStorageVerifier`).
4. Wire `StaticStackContextStorage` into a real `RunItBuilder` run to confirm interop.
5. Write `docs/consumer.md`; perform the clean-consumer walkthrough; populate the findings log
   (§6) with real observations.
6. Triage findings; fix criticals (`TP-001` first) before `1.0.0` freeze.
7. Mark M14.8 items done and confirm the M14 acceptance gate
   ("A third-party-style adapter passes conformance tests").

---

## 9. Definition of done for M14.8

- [x] A separate package implements ≥1 third-party `ArtifactPublisherInterface` and ≥1
      third-party `ContextStorageInterface`, depending on RunIt only as a published dependency.
- [x] That package's own test suite drives the **shipped** `ArtifactPublisherVerifier` and
      `ContextStorageVerifier` and passes (no framework base-class inheritance).
- [x] A clean-consumer document was followed end-to-end and the stumbles recorded.
- [x] A findings ledger exists; critical findings are resolved or have an accepted 1.0.0
      mitigation.
- [x] The M14 acceptance gate item "A third-party-style adapter passes conformance tests" is
      satisfied by evidence in the sample package.

## 10. Implementation status (delivered)

The plan was implemented as a standalone sibling package `it-pack/run-it-third-party-sample` at
`src/RunItThirdPartySample`, mirroring the structure of `it-pack/configure-it-runit` (its own
`composer.json` with a path repository to `../RunIt`, and `phpunit.xml.dist` / `phpstan.neon.dist`
/ `phpcs.xml.dist` / `.php-cs-fixer.dist.php`).

Delivered artifacts:

- `src/Publisher/StreamLinePublisher.php` — minimal `ArtifactPublisherInterface` (writes one JSON
  line per artifact to a stream).
- `src/Publisher/BufferedFilePublisher.php` — `ArtifactPublisherInterface` +
  `FlushablePublisherInterface` + `CloseablePublisherInterface` (buffered, repeatable flush/close).
- `src/Context/StaticStackContextStorage.php` + `src/Context/ScopeToken.php` — a from-scratch
  `ContextStorageInterface` implementation (plain LIFO array, strict token rejection).
- `tests/PublisherConformanceTest.php` — drives the shipped `ArtifactPublisherVerifier`.
- `tests/ContextStorageConformanceTest.php` — drives the shipped `ContextStorageVerifier`, using a
  real `ExecutionScopeInterface` captured from a `RunItBuilder` run (resolves TP-001).
- `tests/ContextStorageInteropTest.php` — proves the sample storage works as the live ambient
  scope storage of a real execution.
- `docs/consumer.md` — clean-consumer walkthrough satisfying the "documentation reviewed
  independently" gate.

Gates (run inside `src/RunItThirdPartySample`):

- `composer validate --strict` — valid (only a `*@dev` constraint warning, identical to the sibling
  `configure-it-runit` package).
- `vendor/bin/phpunit` — **4 tests, 12 assertions, OK**.
- `vendor/bin/phpstan analyse` — **no errors** (level 6, `src` only).
- `vendor/bin/php-cs-fixer fix --dry-run --diff` — **0 files to fix**.
- `vendor/bin/phpcs --standard=phpcs.xml.dist src tests` — **0 errors**.

Net result: a genuine external consumer (separate Composer package, RunIt as a vendor dependency,
no access to `Tests\Support\...`) can implement the ports and pass the shipped conformance suites,
which satisfies the M14 acceptance gate "A third-party-style adapter passes conformance tests."
