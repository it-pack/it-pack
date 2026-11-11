# ConfigureIt Refactoring Plan

Source: [`REVIEW.md`](REVIEW.md), dated 2026-11-11. This plan applies to the
ConfigureIt package; paths and commands below are relative to its root unless
stated otherwise. Recheck findings and dependency versions against the current
checkout before changing code. Cache risks apply when the relevant optional cache
is enabled. Do not treat review metrics or an advisory as proof of exploitability.

## Delivery Rules

- [ ] Deliver phases as small, independently reviewable changes, with regression
  tests for each behavior change before or alongside the fix. Preserve unrelated
  work and do not regenerate architecture baselines to hide violations.
- [ ] Inventory affected public interfaces, constructors, persisted cache formats,
  and consumers before changing them. Record compatibility, upgrade and rollback
  implications; obtain maintainer approval for intentional breaking changes.
- [ ] Run focused tests first, then `composer cs:check`, `composer analyse`,
  `composer architecture`, relevant unit and integration tests, and ultimately
  `composer quality`. Record failures and separately run checks excluded from the
  current quality script. Do not claim a passing full gate when an earlier stage
  prevented later stages from running.
- [ ] Update user-facing documentation for changed behavior, security requirements,
  optional dependencies and migrations. Do not commit generated reports or locks
  unless repository policy explicitly requires them.

## Phase 0: Decisions and Baseline

- [ ] Reproduce the review baseline: validate Composer, run unit and integration
  suites, coverage generation/check, architecture, analysis, formatting, audit,
  and record exact versions, failures, skips and report locations. Run mutation
  separately if feasible; it was not run in the review.
- [ ] Resolve the conflict between review-requested conventions and `AGENTS.md`:
  Yoda comparisons, member order, construction of immutable data, and homes for
  single-use versus shared vocabulary. Record the maintainer's decision before
  changing rules or bulk-reformatting. Until then, follow current repository rules;
  `ConfigurationBuilder.php:196` is the known formatter failure.
- [ ] Decide the public compatibility and release policy for fingerprint contracts,
  constructors, snapshot value restrictions, schema adapters, and cache formats.
  Identify external consumers and supported upgrade paths; the review does not
  establish their inventory or a release/versioning decision.
- [ ] Decide the intended coverage target and denominator: direct unit statements,
  elements and/or methods, threshold, excluded code, and integration reporting.
  The review requests at least 95% assurance but does not establish that method
  coverage or branch coverage is an enforced requirement.
- [ ] Decide CI resource and platform expectations for integration, coverage,
  mutation and permission tests, including how optional dependencies are installed.
  The review does not establish the supported OS matrix or CI time budget.

**Exit:** baseline and decisions are recorded; unresolved decisions remain explicit
blockers for only the affected work, not permission to guess.

## Phase 1: Dependency and Trust Boundaries

- [ ] Re-run `composer security:audit` and `composer why` for `symfony/config`,
  `symfony/filesystem`, and `symfony/yaml`. Map installed development and optional
  consumer dependency paths separately and check current advisory ranges and fixes.
- [ ] Update within declared constraints where possible, respecting ignored package
  lockfiles and existing changes. If a fix requires a constraint change, identify
  the compatibility impact and obtain approval rather than silently widening it.
  Re-run audit, affected adapter tests and the broader suite; document residual
  advisories and the abandoned `doctrine/annotations` dependency or replacement.
- [ ] Trace untrusted inputs to the affected XML, filesystem and YAML facilities;
  document actual exposure and add boundary tests or mitigations where applicable.
  Do not describe a development-only advisory as a confirmed production exploit.
- [ ] Document that `PhpLoader` executes configuration as PHP code and requires
  trusted sources. Inventory optional adapters and their consumer-installed vendor
  packages; verify clear missing-dependency errors and installation instructions,
  including tests in an environment where optional packages are absent.

**Exit:** audit results and unresolved dependency constraints are recorded, and
deployment and adapter trust requirements are documented.

## Phase 2: Cache Correctness and Fallback

Implement and test the two caches independently; preserve business-resolution
errors rather than converting them into cache misses.

### Compiled snapshots

- [ ] In `ConfigurationCompiler` and `CachingResolver`, characterize effective
  source selection, precedence and cache-hit behavior with tests. Preserve source
  order in the cache identity; prove reversing conflicting sources changes the
  winner even when a prior artifact exists.
- [ ] Design a deterministic, versioned fingerprint covering effective source
  identity and content/version, schema rules, configured processors, readers,
  loaders and resolution options. Inventory every input used by resolution before
  declaring the key complete. Do not use class names or stable URIs alone as proof
  of equivalent behavior.
- [ ] Define an opt-in contract or equivalent mechanism for stable fingerprints,
  including custom implementations. For any input that cannot be fingerprinted
  reliably, bypass compiled caching; use bounded lifetime and revalidation only
  if their semantics are explicitly approved and tested. Decide how secrets and
  authorization identity enter fingerprints without exposing credentials.
- [ ] Test stable-URI remote/environment updates and credential revocation, schema
  constraint tightening, changed collaborator configuration, changed options,
  and equivalent inputs yielding stable keys. Verify validation cannot be bypassed
  by an old hit. Include a custom unfingerprintable collaborator and assert the
  chosen safe fallback.
- [ ] Materialize one-shot source iterables once, select defaults identically to
  `ConfigurationResolver`, and reuse the effective sources for fingerprinting and
  resolution. Test empty and nonempty generators on hits and misses, order, and
  source iteration exceptions.
- [ ] Contain failures in fingerprinting and cache reads/writes at the cache
  boundary and fall back to actual resolution where safe. Test throwing caches,
  corrupt PHP artifacts and resolver exceptions; the latter must still reach callers.

### Remote documents

- [ ] Replace lossy filename sanitization in `FileSystemDocumentCache` with a
  collision-resistant digest of the full logical key. Define how tenant and
  authorization identity are supplied without writing raw credentials; the review
  does not establish an existing identity contract. If isolation cannot be proven,
  require separate cache namespaces/directories or bypass sharing.
- [ ] Test distinct formerly colliding URIs, format differences and cross-tenant or
  authorization isolation using a shared cache. Document key-version migration:
  do not read ambiguous legacy filenames as trusted hits; plan safe invalidation
  and rollback without deleting unrelated cache files.
- [ ] Fix null expiry handling to distinguish a present null from a missing field,
  in accordance with `RemoteDocumentCacheInterface`. Replace the contradictory
  test and cover null, expired, fresh and malformed entries.
- [ ] Translate transport failures at the `HttpReader`/client adapter boundary to
  the documented domain read exception while retaining the cause. Exercise real
  adapter failure paths with a stale last-good entry and without one; ensure
  unrelated programming and business errors are not indiscriminately swallowed.
- [ ] Test cache read/write and parse failures for both filesystem caches, including
  corrupt artifacts. Ensure misses and fallback obey each cache's documented
  contract and never mask a genuine resolution failure.

**Exit:** regression tests demonstrate isolation, precedence, invalidation,
generator handling, and fallback; cache version and migration are documented.

## Phase 3: Cache Artifact Security

- [ ] Define the deployment threat model and supported permission semantics before
  choosing an implementation. Existing executable PHP cache artifacts can disclose
  secrets or execute attacker-controlled code if their directory is writable by an
  untrusted party; the review does not establish an incident or supported OSes.
- [ ] Prefer a non-executable serialization format if it can represent the supported
  snapshot/document value model safely; specify type validation and failure behavior.
  If executable artifacts remain, explicitly require a trusted private directory.
  Decide compatibility and migration before changing persisted format.
- [ ] Validate existing as well as newly created cache directories against the agreed
  ownership, symlink and permission policy; enforce restrictive artifact modes
  independent of permissive umask where the platform supports it. Avoid silently
  weakening the policy on unsupported platforms; document how they are handled.
- [ ] Test permissive pre-existing directories, file modes, symlink/path handling,
  unauthorized or invalid artifacts, concurrent writes and failure cleanup as
  supported by the chosen design. Ensure secrets do not appear in diagnostics.
- [ ] Document provisioning, ownership, shared-host restrictions, invalidation and
  rotation. Preserve the Phase 2 fallback distinction between cache failure and
  business failure.

**Exit:** the chosen artifact format and trust model are explicit, tested and
deployable, including any persisted-cache migration.

## Phase 4: Builder, Snapshots and Architecture

- [ ] Characterize `ConfigurationBuilder::withWiring()` after sources, schema,
  processors and options have been set. Preserve all configured state in the new
  builder, or explicitly reject unsafe use if that is the approved contract. Test
  both ordering of calls and original/new builder independence; document any break.
- [ ] Define the supported `ConfigurationSnapshot` value model, including nested
  objects from `PhpLoader`. Choose and document either enforceable deep isolation
  or an explicitly shallow guarantee with a practical isolation strategy. Test
  mutations through original inputs and returned nested values; do not claim PHP
  readonly arrays deep-freeze objects.
- [ ] Inventory actual Core-to-Cache/Execution and Adapter-to-Core dependencies
  against `phparkitect.php`, `AGENTS.md` and `docs/PORTS.md`. Decide the intended
  boundaries and documented exceptions before tightening rules; migrate dependencies
  through contracts and composition roots, not by adding silent exclusions.
- [ ] Expand `NoInjectableConstructorDefaultsTest` beyond its selected file list
  after defining what is a service, immutable data, composition root and documented
  exception. Inject collaborators via wiring while preserving public constructor
  compatibility or following the approved migration. Test the guard with examples
  that must fail and legitimate value-object cases that must pass.
- [ ] Assess `SchemaInterface::getAdaptee()` and `ContextInterface` leakage:
  inventory callers and incompatible pairings, design an adapter-owned capability
  or typed port where justified, and test incompatible pair diagnostics. Do not
  remove a public escape hatch without a consumer migration and approval.
- [ ] Run architecture, static analysis, wiring and consumer-facing regression
  tests after each boundary change; update architecture documentation when the
  approved policy changes.

**Exit:** behavior and compatibility are explicit, and enforcement matches the
agreed architecture rather than merely passing the existing weaker rules.

## Phase 5: Duplication and Complexity

- [ ] Characterize accepted/rejected scalar inputs and exception types/messages in
  `ScalarCaster` and `CastingProcessor`. Extract their shared conversion policy
  through an appropriate contract/value boundary while preserving caller-specific
  exceptions and messages; add parity tests for both entry points.
- [ ] Centralize shared scalar type vocabulary used by `CastingProcessor`,
  `TypedSnapshot` and `NativeConfigurationSchema` in the agreed contract home;
  preserve existing public and serialized string values and cover round trips.
- [ ] Isolate repeated Nette private-property reflection in an appropriate
  vendor-specific adapter boundary. Characterize `NativeConfigurationSchema` and
  `SchemaModelBuilder` separately, retain their different output shapes, and test
  supported Nette versions and failure diagnostics. Do not move vendor mechanics
  into Core.
- [ ] Inspect and characterize each reviewed method with CCN >10 before extracting
  cohesive behavior: `Provenance::fromArray()` and `copy()`;
  `ConsulClient::fetch()`; `NativeCommandRunner::drive()`;
  `GcpSecretManagerClient::fetch()`; `ConfigurationDiffer::summarize()`;
  `EtcdClient::fetch()`; `ConfigurationDiff::summary()`; `XmlLoader::toArray()`;
  `FileSystemCache::get()`; and `FlagDefinition::fromArray()`. Prioritize methods
  touched by earlier fixes; keep error, cleanup, serialization and ordering behavior.
- [ ] Re-run PhpMetrics on the same scope and compare method maxima and behavior
  tests; use complexity as a review signal, not a mandate to split cohesive code.
  Inspect the builder size, resolver coupling and `TempFileFactory` maintainability
  only where a concrete simplification is supported by evidence.
- [ ] Review PHPAssumptions output individually before making changes; its 65
  reported assumptions are not 65 verified defects. Avoid introducing fibers,
  weak references, lazy objects, property hooks or traits without a measured need
  and appropriate lifecycle and concurrency semantics.

**Exit:** duplicated policy has one suitable home, relevant behavior is preserved,
and complexity changes are justified rather than metric-driven churn.

## Phase 6: Coverage, CI and Conventions

- [ ] Diagnose the isolated `CachingResolverTest` coverage attribution leak using
  the actual Codeception/PHPUnit/PCOV versions and effective configuration. Prove
  any metadata or strict-coverage setting is applied by running an isolated test
  that executes a collaborator and checking that only intended code is credited.
  Investigate the review's 2,793 versus 2,796 executable-line denominator mismatch.
- [ ] Separate direct-unit coverage from integration execution and define report
  generation so each gate consumes a fresh report with the agreed source set.
  Add direct tests to meet the approved >=95% threshold and denominator; report
  statement, element and method results separately. Do not claim branch coverage
  without a driver and report that actually measure it.
- [ ] Harden `scripts/coverage_check.php` to reject empty, incomplete, missing or
  stale reports rather than treating zero totals as 100%. Test those failure modes,
  valid boundary thresholds and normal reports; validate completeness against the
  agreed source inventory without assuming every physical line is executable.
- [ ] Update package scripts and the monorepo quality workflow to run unit,
  integration, fresh coverage generation and checking according to the Phase 0
  decision. Verify optional dependencies, failure propagation and test artifacts;
  run the resulting CI-equivalent commands end to end, including mutation and
  audit where required by the repository quality contract.
- [ ] After the convention decision, align formatter and guards with the approved
  policy and fix the known formatter failure. Audit global function imports,
  member ordering, vocabulary, `else`, line lengths and multiple named test symbols
  as appropriate to that policy. Handle generated support and fixtures separately;
  do not infer comprehensive compliance from the existing limited scans.

**Exit:** a clean checkout can reproduce the chosen quality gate; direct coverage
and integration coverage are distinguishable, and failures cannot pass on empty data.

## Final Acceptance

- [ ] Trace every finding in `REVIEW.md` to an implemented change, an explicit
  accepted risk, or a recorded decision/dependency with an owner and follow-up.
- [ ] Run the full approved package and CI-equivalent checks on the final checkout;
  report exact outcomes, skipped tests, audit status, coverage denominators and any
  unrun checks with reasons. Verify documented upgrade and rollback procedures.
- [ ] Summarize public API, constructor, snapshot and cache-format impact, remaining
  risks, and any consumer coordination required before release.
