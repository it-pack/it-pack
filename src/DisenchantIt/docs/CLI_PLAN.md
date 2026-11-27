# DisenchantIt Commands And CLI Plan

Status: proposed, not implemented. This document is an implementation plan, not an API
or release promise. Examples show proposed behavior unless explicitly described as
current. Resolve remaining implementation choices before stabilizing public contracts.

## 1. Purpose And Outcomes

Create an independently installable command package for DisenchantIt that offers both
standalone, programmatically invocable commands and a Symfony Console application via
CommandIt. The same use cases, configuration, results and safety policies must apply
to both entry points. Developers can inspect and fix source locally; CI can check it
without changing the working tree. Cover the full supported DisenchantIt feature
surface, rather than only repeated strings or the simplest rewrite path.

Success means deterministic, explainable results; explicit source and configuration
trust boundaries; no accidental writes; actionable diagnostics; bounded resource
use; compatible independent installation; and tests that demonstrate those properties.
Do not introduce a dependency from DisenchantIt core to RunIt or a console framework.

### Non-goals

- Executing, requiring or autoloading analysed PHP files, including during discovery,
  detection, preview or rewriting. An optional `php -l` subprocess is syntax-only.
- Replacing DisenchantIt's domain decisions with CLI-specific heuristics.
- Treating telemetry as the authoritative command result.
- Claiming cross-file transactional atomicity on a general filesystem.
- Silently enabling executable configuration, remote includes, network access, or
  global OpenTelemetry providers.
- Promising support for capabilities not actually exposed or implemented upstream.

## 2. Evidence And Constraints

Recheck these observations against the target checkout during implementation:

| Area             | Current evidence                                                                                                     | Consequence                                                                                                                                                                                                                       |
|------------------|----------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| SDK              | `src/Contract/DisenchanterInterface.php`, `src/Core/Disenchanter.php`                                                | Caller supplies source text and optional relative path. `detect`, `rewrite` and `rewriteWithSemConv` have different output and dry-run capabilities. No project walker is supplied.                                               |
| Preview          | `rewrite(..., dryRun: true)` returns original text and records a plan; `rewriteWithSemConv` has no dry-run parameter | A recorded plan is not a textual diff. Produce check output by computing proposed content without writing, or add an explicit upstream planning API. Test parity.                                                                 |
| Promotion        | `src/Factory/PromotionTargetFactory.php`                                                                             | Current default composition includes flag-set and backed-enum targets; FQCN `::class` promotion is opt-in. `docs/USER_GUIDE.md` has stale statements. Verify actual support and do not infer it from old prose.                   |
| Scope            | `src/ValueObject/Scope/DisenchantScope.php`, `src/Factory/DetectorFactory.php`                                       | Namespace filtering is not implemented. Relative paths matter for path filtering; default factory does not expose every scope setting.                                                                                            |
| Reports          | `src/Adapter/Reporting/JsonReportRecorder.php`, `SarifRecorder.php`                                                  | Existing artifact recorders retain arrays; current SARIF is not a location-rich findings report. Do not advertise bounded or full SARIF reporting without additional work.                                                        |
| Runtime          | `composer.json`                                                                                                      | DisenchantIt currently requires PHP `^8.5`; some prose says 8.4. Effective CLI constraints must be solved from all actual manifests, not documentation.                                                                           |
| CommandIt        | `../CommandIt/src/SDK/CommandIt.php`, `../CommandIt/src/Adapter/CLI/ConsoleCommandBridge.php`                        | Definitions, handlers and a Symfony bridge exist; Symfony is suggested rather than required. Bridge output and success/failure exits are currently constrained; array options and aliases are not projected.                      |
| Raw argv         | `../CommandIt/src/Adapter/CLI/ArgvInputParser.php`                                                                   | It is a parser, not an application; it can consume a positional after a boolean option as its value. Do not rely on it for the application without an upstream fix and tests.                                                     |
| Configuration    | `../ConfigureIt/src/Core/ConfigurationBuilder.php`                                                                   | When layers are registered, flat sources are ignored. Explicitly compose and test every layer.                                                                                                                                    |
| ConfigureItRunit | `../ConfigureItRunit/src/ConfigureItExecutions.php`, its integration test                                            | It instruments configuration resolution, not the entire command. Pass the callback reporter to `builder->build($reporter)->resolve()`; the helper's example suggesting `resolve($reporter)` is inconsistent with the current API. |
| Config trust     | `../ConfigureIt/src/Adapter/PhpLoader.php`                                                                           | PHP configuration is executed. Analysed code and trusted executable configuration are distinct boundaries; default to non-executable data.                                                                                        |
| RunIt            | `../RunIt/README.md`                                                                                                 | Default classification, provenance and publication policies can drop artifacts. Delivery and stage-level telemetry cannot be assumed.                                                                                             |

The DisenchantIt source-text invariant and existing contracts remain authoritative.
Correct conflicting guide/manifest statements as a separately reviewed prerequisite.

## 3. Architecture And Package Boundaries

Proposed package: `it-pack/disenchant-it-command`, directory
`src/DisenchantItCommand`, namespace and executable name to be confirmed with the
maintainer. Choose Composer constraints only after a real dependency solve. Require
Symfony Console explicitly in whichever installable package actually supplies the
Symfony application; CommandIt's suggestion does not install it. Start with one
package if optional dependencies can be expressed honestly. If consumers need a
Symfony-free installation, split a command package and console package rather than
pretending a mandatory dependency is optional. Decide this before publishing APIs.

```text
Programmatic caller --> CommandIt definitions / bus --> use-case handlers
Symfony application --> CommandIt console projection --> same bus / handlers
                                                   |--> configuration resolution
                                                   |--> source discovery + IO ports
                                                   |--> DisenchantIt SDK
                                                   |--> result/report ports
                                                   +--> optional execution adapters
```

- Define independently registrable commands with typed input and result contracts;
  a caller may invoke one without creating a Symfony application or using globals.
- Keep CLI parsing, rendering and exit mapping outside domain handlers. Compose
  factories and ports at the application boundary; provide testable filesystem,
  clock, cancellation and output seams where there is a concrete need.
- Use CommandIt's registry and bus. Put generic command, result and Symfony
  projection capabilities in CommandIt; keep CLI-specific behavior in this package.
  Use a thin package-owned adapter where appropriate without duplicating logic.
- Integrate ConfigureIt at the boundary, and ConfigureItRunit for actual configuration
  resolution when RunIt is enabled. Model the whole command execution separately.
- DeriveIt and DeriveItRunit are not dependencies of this CLI; its commands do
  not require derivation.
- External telemetry and format adapters do not leak into DisenchantIt contracts.
  Optional integration packages or composition modules must have explicit dependency
  and failure boundaries.

## 4. Product Contract

Proposed application name `disenchant`; command names and switches are subject to
Phase 0 compatibility testing:

| Command             | Purpose                                                                         | Writes source?             |
|---------------------|---------------------------------------------------------------------------------|----------------------------|
| `detect [paths...]` | Report candidates and classifications, without promoting them                   | Never                      |
| `check [paths...]`  | Compute the same final output as `fix`, report whether changes are needed       | Never                      |
| `fix [paths...]`    | Compute, validate and safely apply proposed source and optional SemConv changes | Yes, only after validation |
| `config:validate`   | Resolve and validate effective configuration without scanning source            | Never                      |
| `config:explain`    | Show redacted effective values and their provenance                             | Never                      |

`fix --dry-run` must be equivalent to `check` for proposed content and zero writes;
it must not merely invoke the SDK's recorded-plan mode and claim to show the final
diff. `detect` findings do not themselves imply rewriteable changes. A check with
SemConv enabled computes source and registry output in memory or bounded staging,
without mutating existing files, and compares both. A fix on a clean tree is a no-op;
the second fix and following check must also be clean.

Illustrative interface, not a claim about the present Symfony bridge:

```sh
vendor/bin/disenchant detect src --format=json
vendor/bin/disenchant check src --config=disenchant.json --format=json
vendor/bin/disenchant fix src --min-occurrences=2 --verify-syntax
vendor/bin/disenchant fix src --dry-run
vendor/bin/disenchant config:explain --config=disenchant.json
```

Support path arguments, config location, minimum occurrences, explicit feature and
scope controls, output format, verbosity, and verification as the corresponding
capability becomes available. Repeatable paths/options and mutually exclusive flags
need deliberate projection and parser tests; do not assume the current bridge handles
them. Support non-interactive invocation by default, honor standard output streams,
and avoid prompts in CI. `--help` and `--version` must work without scanning or
requiring project config. Design command naming and a potential `--` terminator test
for paths beginning with `-`.

### Exit codes

Proposed stable process contract: `0` successful and clean (or successful `fix`,
including changes); `1` check/dry-run has proposed changes; `2` invalid usage or
configuration; `3` processing, verification or IO failure, including incomplete
application; `130` interruption where supported. Errors take precedence over changes.
Detection returns `0` on successful analysis regardless of findings; it is not a CI
gate unless an explicit future policy says otherwise. Publish exact precedence and
map all typed results consistently; do not rely on the stock bridge's binary exit
mapping. Machine output goes to stdout, diagnostics to stderr, without ANSI/progress
contamination. Broken pipes and write failures must not report success.

## 5. Feature Coverage And Gaps

The plan's acceptance matrix must be checked against the actual SDK and tests at
implementation time. Expose only supported controls, and distinguish defaults from
opt-in features.

| Capability          | Required CLI treatment                                                                                                                                                                           |
|---------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Detection           | Strings, contextual numerics and raw tokens; existing exclusions, roles, naming contexts, paths and thresholds. Preserve SDK semantics rather than reclassify.                                   |
| Promotions          | Constant, currently supported backed-enum/flag-set strategies and opt-in FQCN conversion; report what was actually selected. Test independently and in combinations.                             |
| Naming              | Wire supported naming policy and dictionary; validate collisions and invalid identifiers via the SDK; do not invent fallback names silently.                                                     |
| Scope               | Deterministic relative paths, includes/excludes, excluded values, test-directory and heredoc controls where exposed. Namespace filters remain unavailable until implemented and tested upstream. |
| SemConv             | Existing registry merge, collisions, aliasing and preservation of prior entries; deterministic cross-file accumulation. Do not imply enum artifacts become SemConv entries.                      |
| Diagnostics         | Candidate location where truly available, classification, selected action, skipped reason and processing errors; never fabricate precise locations.                                              |
| Verification        | Optional independent syntax check of proposed content and registry; do not claim `php -l` proves semantic correctness or executes project tests.                                                 |
| Execution recording | Support a no-op default and optional recorder, subject to boundedness and redaction; artifacts are not the primary report.                                                                       |

Some controls are not configurable through `DisenchanterFactory::create()` today.
Prefer a reviewed upstream factory/configuration extension with tests and a backward
compatibility decision over duplicating internal assembly. If lower-level composition
is chosen, document its compatibility boundary and verify parity with the default
factory. Unsupported options must fail validation, never silently do nothing.

## 6. Configuration Contract

Use ConfigureIt for resolution and validation, with one explicit layer strategy:
defaults < selected config file < documented environment variables < explicitly
supplied CLI arguments/options. Track absence separately from `false`, zero, empty
string and empty list; Symfony defaults must not accidentally override file values.
Specify list replacement versus append per field (default: replace); reject unknown
keys, duplicate conflicting options and invalid combinations. Test each precedence
pair and end-to-end mixed-source cases, including explicit negation of booleans.

Proposed JSON configuration, subject to the final schema and adapter capabilities:

```json
{
  "version": 1,
  "paths": ["src"],
  "exclude": ["vendor/**", "var/**"],
  "minOccurrences": 2,
  "output": {"format": "text"},
  "verification": {"syntax": true},
  "semconv": {"enabled": false}
}
```

- Define the schema, types, defaults, merge behavior, schema-version migrations,
  bounds and path bases in code and documentation. Validate before source reads or
  writes. CLI positional paths override configured paths only when supplied.
- Default to a non-executable format such as JSON with a size limit. Config discovery
  must have a deterministic search boundary and ambiguity/error policy. Relative
  config paths resolve against the configuration file's directory; CLI paths resolve
  against the invocation directory, unless explicitly specified otherwise.
- Environment variables use a documented allowlist and parsing rules; do not dump
  the environment. Config explanation redacts sensitive values and reports origin,
  not secrets. Errors must not echo untrusted source/config payloads by default.
- Executable PHP config is disabled by default; if added, require an explicit trusted
  opt-in, disclose code execution, and never confuse it with analysed files. Disable
  includes by default; if enabled later, constrain roots, cycles, size, nesting,
  symlinks and remote access. Do not load project bootstrap or Composer autoloaders
  from the analysed tree.
- To instrument resolution, build the resolver inside the
  `ConfigureItExecutions::run` callback using the supplied reporter. Establish which
  execution is parent/child using supported RunIt context APIs; do not assume this
  helper alone covers the CLI lifecycle.

## 7. File And Rewrite Safety

1. Resolve allowed roots and explicit paths; normalize paths and define case and
   separator behavior. Pass a stable root-relative path to the SDK. Sort discovered
   files deterministically. Define overlapping roots, duplicate paths, unreadable
   files, unsupported extensions and empty matches; do not silently skip errors.
2. Default to regular `.php` files, no symlink following, and explicit exclusions for
   dependencies, generated files and the configured registry. Enforce root boundaries
   after canonicalization, including registry destinations. Define explicit-file
   versus excluded-path behavior and test both. Avoid scanning the registry as input.
3. Read each file as bytes with configured per-file and total limits; fingerprint the
   original and preserve encoding/newlines and permissions where supported. Detect
   invalid inputs rather than corrupt them. Plan files in stable order and thread the
   evolving SemConv source through each SDK call in memory or bounded staging.
4. Compute all proposed outputs, including a new or updated registry. Detect name
   collisions, conflicting destinations and inconsistent changes before writing.
   Compare original/proposed bytes, make a deterministic plan and validate results.
   If a plan exceeds resource limits, fail explicitly, not partially.
5. Optionally syntax-check staged output through an isolated subprocess with an
   argument vector, timeout and bounded output. The process must not run source. A
   parse, lint, SemConv or planning failure prevents all writes in the default mode.
6. Before each commit, revalidate file identity, type and content fingerprint; reject
   concurrent modification or path/symlink substitution. Stage temporary files in the
   destination directory with restrictive permissions, flush as supported, preserve
   relevant metadata and use same-filesystem atomic rename where supported. Define
   platform-specific behavior and refuse unsafe fallbacks by default.
7. Multi-file replacement is not atomic. Define write ordering and report detected
   failures and known partial application, including affected paths. Do not perform
   automatic rollback. Before fixing, users who need to restore the exact pre-fix
   state should create a checkpoint of every affected file, including uncommitted,
   untracked and ignored files: commit or stash tracked and untracked changes as
   appropriate, and separately back up ignored files (or use a full file backup).
   Inspect the working tree and checkpoint before restoring, to avoid overwriting
   pre-existing work. A clean tracked tree is not required; journaling and automatic
   recovery are not release requirements.

Check, detect and dry-run must not create source, registry, lock, backup or staging
files in the target tree; if syntax verification needs temporary files, use a private
temporary area, clean up and document that it is not literally zero filesystem IO.
No changes to analyzed source can be considered verified until preview/fix parity,
lint and idempotency tests pass. A diff view is a future option, not an initial-release
requirement.

## 8. Reporting, Security And Operations

- Define a versioned, vendor-neutral result: command status, counts, findings, planned
  changes, bounded diagnostics, verification and partial-write status.
  Assign stable diagnostic codes; avoid source snippets, literal values and absolute
  paths by default. Provide explicit opt-in for sensitive detail, with warnings.
- Provide concise human-readable text and structured JSON. Document absent locations
  rather than synthesizing them. SARIF and diff views are future options, not initial
  release requirements.
- Make practical report and recorder limits configurable; choose initial defaults
  during implementation and adjust them with use. Stream or bound retained data.
  On limits, fail or explicitly mark truncation; never report a complete clean scan.
  Default metric dimensions must not include paths, literal values or identifiers.
- Wrap a command in a RunIt execution if configured, with bounded activities for
  resolve, discover, plan, verify, apply and report as actually supported. Restore
  context in `finally`, preserve the original failure, and make cancellation and
  timeouts explicit. Use ConfigureItRunit's bridge for configuration, recognizing
  its current synthetic stage events and retention limitations; do not promise true
  measured scopes without verification or upstream changes.
- Default to no global providers or network export. Publication is optional and best
  effort: failures must not change successful business results or mask original errors.
  Route publication failures through RunIt's configurable health reporting and its
  fallback path; guard against reporter failure and re-entry. Health reporting can
  be disabled when configured. Storage and retention belong to the destination or CI
  environment. Never use telemetry delivery as proof a scan completed.
- Document Linux as the actively supported OS and the actual resolved PHP version.
  Treat other environments as unverified until their filesystem semantics are tested.

## 9. Phased Implementation

Each phase has a reviewable deliverable and a gate; a later phase does not convert a
failed earlier gate into a passing release.

### Phase 0: Contracts And Decisions

1. Inventory public SDK, adapter behavior, supported PHP/Composer constraints, tests,
   documentation drift and sibling integration versions. Produce a traceable feature
   matrix including defaults and unsupported controls.
2. Decide package name/split, command names, outputs, exit codes, schema/versioning,
   configuration trust and filesystem support. DeriveIt is not needed.
3. Prototype CommandIt's Symfony bridge against exit codes, typed results, array
   inputs, streams and safe errors. Record whether an upstream change or a thin
   custom projection is required. Similarly test ConfigureIt layer precedence.
4. Document consequential package, integration and execution decisions. Identify
   required upstream changes; the maintainer approves them.

Gate: each feature maps to current behavior or a specific upstream task.

### Phase 1: Package Skeleton And Standalone Commands

1. Scaffold Composer metadata, PSR-4 namespace, minimal executable wiring, package
   documentation and monorepo path/release configuration as appropriate. Declare
   direct dependencies honestly and validate a clean consumer install.
2. Define typed requests/results, stable diagnostic codes and a composition root;
   register commands with CommandIt and exercise individual handlers via its bus.
3. Add the Symfony application via the approved projection, help/version and correct
   process exit mapping; test in subprocesses. Do not duplicate handler logic.

Gate: programmatic and CLI entry points execute the same fake use case, with proven
argument, stream and exit equivalence; no filesystem mutation occurs.

### Phase 2: Configuration And Discovery

1. Implement typed schema, layers and provenance, config validation/explanation and
   safe JSON loading. Wire precedence with explicit presence tracking.
2. Implement deterministic, bounded source discovery and safe path handling with
   injected IO; distinguish missing files, exclusions and empty scans.
3. Exercise malformed/untrusted configuration, path traversal, symlinks, unreadable
   files, duplicate paths, invocation from different directories and resource limits.

Gate: exact resolved inputs and paths are reproducible; invalid config/discovery fails
before any writes. Explain output does not reveal secrets.

### Phase 3: Detection And Reports

1. Connect SDK detection and the minimum necessary factory/configuration extensions;
   honor the feature matrix without changing DisenchantIt semantics.
2. Build text and structured JSON reports with configurable limits. Separate
   findings from execution artifacts; SARIF may follow later.
3. Add source fixtures for strings, numeric contexts, enums, exclusions, naming,
   multiple classes and parsing failures; test deterministic ordering and privacy.

Gate: standalone and console detection agree, are read-only and report errors without
marking incomplete analysis clean.

### Phase 4: Check And Fix

1. Implement one shared plan function that computes proposed source bytes without
   writes, including SDK promotion targets and opt-in FQCN behavior. Define a reliable
   change predicate based on final content, not merely candidates or recorder events.
2. Implement check, fix dry-run and fix from the same plan; add optional syntax
   verification and guarded per-file replacement. Respect stated exit precedence.
3. Test golden preview/fix byte parity, no-change behavior, idempotency, original
   exceptions, failed verification, races and interrupted/failed writes.

Gate: check and dry-run never mutate target files, match a subsequent fix on unchanged
input, and failures are explicit. Document the limitations of multi-file commits.

### Phase 5: SemConv And Complete Feature Parity

1. Add deterministic in-memory/staged cross-file registry accumulation and aliasing,
   preserving existing entries. Ensure check previews both registry and source.
2. Cover new/existing registry, identical/different-value collisions, alias
   idempotency, scope exclusions, alternate paths and registry/source write conflicts.
3. Complete supported scope, naming, dictionary and promotion configuration. Extend
   SDK composition for missing controls; namespace filtering remains a feature choice.
4. Test write failures and document partial application and manual recovery. Multi-file
   fixes are in scope for the initial release without a journal.

Gate: coverage matrix is complete for supported SDK features; SemConv check/fix parity
and second-run idempotency pass on representative projects.

### Phase 6: Execution, Hardening And Release

1. Wire optional RunIt execution and ConfigureItRunit configuration reporting.
   Verify isolation, supported parent/child relationships, configurable limits,
   best-effort publication, health/fallback reporting (including reporter failure and
   re-entry), and preservation of business results and original errors.
2. Run contract, integration, subprocess, security, concurrency and failure-injection
   suites; validate machine schemas and exercise CI on clean and dirty projects.
3. Add reproducible large-tree benchmarks and memory limits; report measured results,
   not speculative performance claims. Check package installation from a clean
   consumer without monorepo path repositories.
4. Document migration, examples, limitations, support policy, security disclosure,
   exit codes and recovery. Publish compatibility and release notes with resolved
   versions; run the package's actual Composer quality scripts and audit.

Gate: all release criteria below pass, unsupported cases are explicit, and maintainers
approve the package and any cross-package contract changes.

## 10. Verification Matrix And Release Criteria

| Layer           | Required evidence                                                                                                                                                                             |
|-----------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Unit/contract   | Schema precedence and absence, command inputs/results, path policies, change predicate, formatting, exit mapping, redaction and limits.                                                       |
| SDK integration | All supported promotion targets and options, source safety, scope path behavior, existing constants, enum backing exclusion, raw numeric tokens, naming, SemConv merge and collisions.        |
| CLI subprocess  | Real executable, help/version, argv edge cases, stdout/stderr separation, every exit category, JSON schema, no colors in machine output and CI non-interactivity.                       |
| Filesystem      | Symlink and traversal attacks, permissions, same-directory staging, concurrent edit and substitution, failed rename/cleanup, interruption, partial application and metadata preservation. |
| Preview         | Detect/check/dry-run never modify target tree; check and fix plans match; unchanged outputs stay byte-identical; second fix/check clean.                                                      |
| Security        | Config injection, executable config opt-in, include policy, path escape, secret-bearing literals/config, report/telemetry redaction, bounded output and subprocess timeout.                   |
| Resilience      | Parse and lint failures, malformed registry, publication failures, cancellation, resource exhaustion and report write errors preserve correct status and original failure.                    |
| Compatibility   | Supported Linux/PHP matrix from manifests, clean Composer install, independently usable commands, Symfony dependency resolution and supported sibling versions.                               |
| Quality         | Codeception suites, PHPStan, PHPArkitect, coding style, mutation testing and security audit via actual package scripts; document missing tools or failures.                                   |

Acceptance requires no source execution/autoloading, no silent skips or truncation,
documented API compatibility decisions, check/fix parity, a documented partial-failure contract,
deterministic safe reports, and a tested installation and CI example. Full project
test suites are consumer-owned and separate from syntax verification.

## 11. Decisions And Remaining Choices

1. **Package:** A Symfony-free installation is not required. The command package
   may depend directly on `symfony/console`.
2. **DeriveIt:** Neither DeriveIt nor DeriveItRunit is needed for this CLI.
3. **SDK composition:** The CLI is a downstream consumer and may drive additions
   to DisenchantIt. Expose supported scope controls through the SDK composition API;
   the maintainer approves required package changes. Namespace filtering is not
   implemented and remains a separate feature decision. Do not expose it as working
   until implemented and tested.
4. **Multi-file SemConv fixes:** Include them in the initial release. Prepare and
   validate proposed changes before writing; report detected failures and known
   partial application. Per-file replacement is sufficient. Batch atomicity,
   journaling and automated rollback/recovery are out of scope. For exact manual
   recovery, users should checkpoint affected files before a fix, including tracked,
   untracked and ignored files, as described in section 7.
5. **Reports:** Default reports contain available finding metadata, not literal
   values or source snippets; allow explicit source-detail opt-in where supported.
   Make practical limits configurable, with initial defaults informed by use. Provide
   human-readable output and structured JSON. Diff views and SARIF may come later.
   The configured destination or CI environment owns storage and retention.
6. **CommandIt:** Put generic command, result and Symfony projection improvements in
   CommandIt. Keep DisenchantIt-specific CLI behavior in this package and domain
   behavior in DisenchantIt. Use a thin package-owned adapter where appropriate.
7. **RunIt:** Integrate from the outset with optional observation. Test supported
   command/configuration parent-child relationships against the resolved versions.
   Artifact publication is best effort; durable delivery is not required, and
   publication failure must not change the business result or mask its error. Use
   configurable health reporting and its fallback path, and test reporter failures.

Remaining choices include namespace filtering, exact configurable limits and defaults,
and final command names and exit codes. Keep DisenchantIt core independent of RunIt
and console frameworks, and preserve its source-text safety invariant.
