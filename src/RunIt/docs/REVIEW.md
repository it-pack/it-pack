# RunIt Project Review

**Review date:** 2026-11-05

**Revision:** `aee7ae3f9d45da455d90c8b06adf7ef5dba205bc`

**Scope:** `src/RunIt`, its package tooling, tests, and relevant monorepo CI.

## Assumptions and limits

- Findings describe the files and installed dependencies observed during the review, not a certification of every code path. The package's `composer.json` changed concurrently during the review; commands and observations should be rerun against the final working tree before release.
- The 95% requirement is interpreted as **unit-only statement coverage** across configured production sources. Unit-suite isolation and attribution of coverage to the class directly under test are separate requirements.
- The public API is frozen at the release candidate. A failing compatibility check is not a reason to regenerate its baseline without a deliberate compatibility decision.
- Severity reflects potential impact and exposure, not a measured frequency. Recommendations do not authorize breaking contracts or changing domain semantics without a compatibility decision.
- This is a targeted, multi-track review, not an exhaustive proof that every rule holds in every PHP file. Metrics and examples below identify where to investigate further; they are not an exhaustive inventory of all violations.

## Executive assessment

RunIt has a coherent execution vocabulary, explicit ports, composable adapters, typed domain objects, a substantial test suite, and a working architecture gate. Its integration dependencies are optional for consumers. However, security ordering, bounded buffering, failure visibility, lifecycle reporting, coverage, and release gates need attention before a stable release. The current API check detects a breaking interface addition; static analysis and the coding-style gate also fail.

## Prioritized findings

### High: Throwable redaction misses nested failure details

`src/Application/Publication/DefaultArtifactRedactor.php:56-65` only checks a top-level `throwable`. Execution normalization nests the failure under its result (`src/Domain/Execution/Execution.php:182`), so a failed execution can remain unchanged by the default redactor. The OpenTelemetry execution mapper can export its message and trace (`src/Adapter/OpenTelemetry/Trace/DefaultExecutionSpanMapper.php:85-91`). Separately, `maskThrowable()` replaces only the immediate message (`DefaultArtifactRedactor.php:158-166`), while throwable normalization includes previous causes and traces (`src/Domain/Shared/ThrowableDescription.php:77-85`); the PSR-3 observation adapter can pass those nested details to a logger (`src/Adapter/Psr/Log/ObservationLoggerPublisher.php:91-115`). Exposure depends on the artifact, configuration, and sink.

Redact throwable details at every supported artifact shape and recursively handle causes. Define an explicit trace policy rather than assuming masking the top-level message is sufficient. Add negative export tests containing secrets in the execution result, previous causes, and trace, including OpenTelemetry and PSR-3 paths.

### High: Redaction occurs after the sampling decision

`src/Application/Factory/PublisherStackFactory.php:33-39` wraps the redactor in the sampler. The outer sampler invokes the policy on the original artifact before forwarding it (`src/Application/Publication/SamplingArtifactPublisher.php:39-42`); redaction happens only inside `RedactingArtifactPublisher::publish()` (`src/Application/Publication/RedactingArtifactPublisher.php:37-40`). This contradicts the promised redaction-before-sampling order in `src/Contract/Port/PublisherStackFactoryInterface.php:11-16` and the builder's default-security description (`src/SDK/Builder/RunItBuilder.php:301-305`). A custom sampling policy can inspect sensitive data that the sink would otherwise receive redacted. This does not establish that the sink receives unredacted data.

Decide the security contract explicitly and align decorator order, policy expectations, and documentation. Add a spy sampling policy test using a sensitive artifact and verify both the policy's input and the sink's projection. Reversing the order may affect sampling behavior and cost; assess that compatibility tradeoff.

### High: Buffer byte limit is not a hard admission limit

`src/Application/Publication/BufferedArtifactPublisher.php:87-98` checks the existing buffer with `isFull()` before adding the incoming item's estimate; `isFull()` compares only the current total (`:176-179`). An item can push the buffer beyond `maxEstimatedBytes`, including when it is the first item. Replacement paths append after removing at most one item (`:218-245`), which also need not make enough space. This contradicts the hard-limit promise in `src/Application/Publication/BufferConfiguration.php:7-11` and can increase memory pressure under large artifacts.

Test projected size before admission for every overflow strategy, including an individually oversized item and cases requiring multiple evictions. Treat the byte estimator as an estimate, not a guarantee of exact process memory consumption.

### High: Default publication failures can be silent

`src/Application/Runner/DefaultExecutionRunner.php:135-146` supplies a health reporter only when a callback is configured. With the default null callback, `FailSafeArtifactPublisher` reports through a nullable health channel (`src/Application/Publication/FailSafeArtifactPublisher.php:58-67`) and `FailOpenPolicy` drops the failure (`src/Application/Publication/FailOpenPolicy.php:10-22`). `reportFailure()` also returns without reporting if the callback is absent (`DefaultExecutionRunner.php:370-374`). A broken publisher can therefore lose telemetry without an operator-visible signal, despite the intended fail-open-with-fallback behavior.

Provide a recursion-safe fallback health path for unconfigured callbacks, preserving the original business result and exception. Test failure of the publisher and failure of the health callback independently.

### High: The frozen interface baseline detects a breaking change

`src/Contract/Port/ContextTokenInterface.php:19-25` requires `id(): int`. `composer api:check` reports this as a method added to the public interface, breaking existing third-party implementations; it also reports four implementation method additions. Existing implementers can fail to load after an upgrade. The interface's introductory comment still says it carries no behavior (`:8-13`).

Resolve the compatibility issue deliberately, for example with an internal identity mechanism or an explicitly versioned contract, and test an external implementation. Do not mask the break by simply regenerating the API baseline.

### High: Unit coverage is below the required floor

`composer coverage` ran 813 unit tests and measured **3,558 of 4,035 statements (88.18%)**, 6.82 percentage points below 95%. With this denominator, at least 276 more statements need coverage to reach the threshold. The command passed without enforcing a threshold. The historical **95.24%** in `docs/QA.md:9-16` combined four suites and is not comparable to this unit-only measurement.

Add meaningful unit tests around uncovered behavior, especially security and failure paths, and enforce the floor on unit-only coverage in CI. Both the package and monorepo quality workflows run tests without a unit-coverage threshold (`.github/workflows/quality.yml:19-33`, `../../.github/workflows/quality.yml:72-98`). Aggregate unit coverage does not establish that each test class credits only its intended subject. Audit test-level coverage metadata and indirect coverage explicitly; no complete per-test attribution audit was performed.

### High: Runtime-specific context storage is required for concurrency

The SDK defaults to a single `StackContextStorage` (`src/SDK/Builder/RunItBuilder.php:283`), which is explicitly designed for synchronous nesting and keeps one shared stack (`src/Application/Propagation/StackContextStorage.php:12-18,25-28,37-67`). Interleaved Fibers or coroutines sharing this default can observe another execution's scope or detach out of order. This is conditional on using the synchronous default in a concurrent runtime; the project provides dedicated Fiber and coroutine adapters.

Make the configuration requirement conspicuous at the builder entry point and in concurrency documentation. Add an interleaving regression test comparing the default with the runtime-specific storage; consider detecting misuse without silently changing frozen behavior.

### Medium: Terminal execution can disappear when snapshot creation fails

`src/Application/Runner/DefaultExecutionRunner.php:339-351` creates a terminal snapshot before publishing the completed execution and returns if snapshot creation throws. A snapshot factory failure can thus suppress an otherwise available terminal execution artifact. Downstream consumers may see an execution start without a terminal outcome.

Publish the completed execution independently of terminal snapshot creation, preserving original exceptions and the configured failure policy. Add a failure-injection test for the snapshot factory.

### Medium: Open-activity policy's message and outcome disagree

`src/Application/Policy/FailExecutionOnOpenActivitiesPolicy.php:20-32` records a critical observation saying the execution is flagged as failed, but does not change its status. The successful runner path subsequently transitions to `ExecutionSuccess` (`src/Application/Runner/DefaultExecutionRunner.php:263-279`). The class documentation describes an observation-based failure signal rather than a changed business result, so the intended status semantics need an explicit decision.

Align the policy name, message, documentation, and terminal outcome; add a test asserting both the business result and terminal status. Treat any status change as compatibility-sensitive.

### Medium: Queue draining performs repeated array shifts

`src/Application/Publication/BufferedArtifactPublisher.php:122-127,310-331` repeatedly calls `array_shift()` on a PHP array. Reindexing the remaining entries can make a large drain or shutdown quadratic in queue length. The actual cost depends on configured buffer size and workload; no performance improvement is claimed without measurement.

Benchmark realistic batch sizes and overflow patterns before changing representation. If warranted, use a queue or head index while preserving order, priority eviction, and drop reporting.

### Medium: OpenTelemetry's default span-context store is unbounded

`src/Adapter/OpenTelemetry/TelemetryPublisher.php:58-65` creates an in-memory store by default. `src/Adapter/OpenTelemetry/InMemoryExecutionSpanStore.php:23-36` retains an entry for each recorded execution and activity and exposes no removal or size bound. In a long-lived publisher, unique identities accumulate with traffic even though entries hold span contexts rather than live spans.

Introduce a bounded or lifecycle-aware retention strategy and document the effect of eviction on delayed parent lookup. Verify persistent-worker memory behavior and benchmark any hot-path change.

### Medium: Redacted observations can disappear from OpenTelemetry

When redaction changes an artifact, the redactor returns a generic `RedactedArtifact` (`src/Application/Publication/DefaultArtifactRedactor.php:94-98`). The OpenTelemetry publisher recognizes generic redacted occurrences and snapshots, but not observations (`src/Adapter/OpenTelemetry/TelemetryPublisher.php:111-140`). A redacted observation can therefore be silently dropped instead of safely mapped.

Preserve sufficient safe type information to route redacted observations, or define and report an explicit drop policy. Add tests for redacted observations without reintroducing sensitive fields.

### Medium: Quality gates fail and an abandoned dependency remains

On PHP 8.5.12, `composer analyse` reported **137 errors**, including invalid test-fixture type names and unimported references. `composer cs:check` reported two test formatting failures. After the dependency update, `composer audit` on 2026-11-05 reported **no security vulnerability advisories**, but still identified `doctrine/annotations` as abandoned. `composer why doctrine/annotations --tree` attributes it to the development dependency chain `just-bases/php-qa-base` → `phpbench/phpbench` → `doctrine/annotations`, not a direct runtime dependency. The earlier audit reported six advisories across four packages; that result has been superseded by the updated audit. Advisory data changes over time.

Repair the static-analysis and style failures without broad suppressions. Track the abandoned transitive dependency through PHPBench and the shared QA base, and continue auditing the final lock and deployment dependency set.

### Medium: Architecture gate covers less than the architecture prose implies

The architecture check passed, but `phparkitect.php:14-19,41-57` groups `Contract\Port` with `Contract` and permits dependencies in both directions between Domain and BehaviorDomain and between Application and BehaviorApplication. A passing result therefore does not independently prove all of the stricter boundaries described in `docs/architecture/hexagonal-architecture.md:26-37`. That document also refers to deleted DepTrac configuration (`:61-64,107-109`), while the executable gate uses PHPArkitect.

Model the intended distinctions and directions explicitly, first inventorying real dependencies. Update the architecture and QA documentation to describe the current tooling; do not add exemptions merely to make a stricter check pass.

## Maintainability and design

- **Duplication and literals:** `src/Adapter/OpenTelemetry/Logs/DefaultOccurrenceLogMapper.php:150-160,181-185,193-203,218-222` checks normalized keys using `ArtifactField` constants but reads some of the same keys using string literals. They currently agree, but paired checks and reads can drift. Reuse the existing constants without changing serialized keys. Similar redacted occurrence/snapshot mapping blocks merit careful consolidation only where their semantics actually match.
- **Assembly complexity:** `DefaultExecutionRunner` has 23 constructor parameters (`src/Application/Runner/DefaultExecutionRunner.php:105-134`) and the SDK wires them individually (`src/SDK/Builder/RunItBuilder.php:314-338`). Consider an internal scope-creation responsibility after tests establish its seams; do not add an untyped dependency bag or break the public constructor solely to reduce its parameter count.
- **Factories and DI:** Ports are injected throughout the runner, and the builder provides explicit assembly seams. The requested stricter rule that all object creation occur only in factories or builders is not met: for example, the runner constructs publishers, a policy, and an engine (`DefaultExecutionRunner.php:135-147`), while Fiber storage creates tokens (`src/Adapter/Fiber/FiberContextStorage.php:47-53`). If adopting this rule, distinguish replaceable services from small value objects and runtime tokens, and assess public-API impact before introducing more factories.
- **Interfaces:** Service ports and many policies are interface-backed, but an exhaustive class-by-class DI audit was not completed. `docs/QA.md:130-147` describes intentional private-leaf exemptions. Do not infer that every concrete class needs a separate interface where there is no substitutable seam.
- **Requested conventions:** The imported-reference requirement is not universally met: PHPStan reports inline `\Throwable` references, for example `src/Application/Publication/FailOpenPolicy.php:20`. The requested declaration order differs from the project's documented rule (`docs/QA.md:63` puts static methods before instance methods); `RunItBuilder::create()` at `src/SDK/Builder/RunItBuilder.php:94` precedes instance methods. Resolve the standard before changing frozen surfaces. No comprehensive inventory was completed for abstract classes, multi-type files, `else`/`elseif`, Yoda conditions, every literal, or argument layout; these are **not verified as compliant**.
- **Line length:** `composer lint:length` passes for its configured PHP scan. Its implementation excludes comments, generated files, and other paths (`tools/lint/line-length.php:11-14,31-48,73-81`), so this is not proof that every line in every code file is at most 120 characters.
- **Modern PHP:** The code already uses readonly classes, Fibers, WeakMap, traits, constructor promotion, and `match`; `src/Adapter/Fiber/FiberContextStorage.php:15-35` is a concrete example. Use property hooks, lazy objects, and weak references only where they clarify an invariant or solve a measured lifecycle/cost problem. Neither mandatory hooks nor factory proliferation is inherently an improvement. Benchmark hot-path changes and preserve explicit context isolation.

## Metrics

`vendor/bin/phpmetrics --composer=false --report-json=/tmp/kilo/runit-phpmetrics.json --report-csv=/tmp/kilo/runit-phpmetrics.csv --report-summary-json=/tmp/kilo/runit-phpmetrics-summary.json src` ran with local PhpMetrics 2.11.0. It reported 232 classes, 149 interfaces, 720 methods, and mean class cyclomatic complexity 4.13. Its highest class CCNs were `DefaultOccurrenceLogMapper` 55, `DefaultArtifactRedactor` 28, `DefaultExecutionRunner` 26, `ObservationLoggerPublisher` 25, and `BufferedArtifactPublisher` 21. Class CCN is not method CCN.

The following classes contain at least one measured method with CCN greater than 10; the value is the **maximum method CCN**, not a named-method inventory:

| Class | Maximum method CCN |
| --- | ---: |
| `Adapter\OpenTelemetry\Logs\DefaultOccurrenceLogMapper` | 20 |
| `Application\Publication\DefaultArtifactRedactor` | 18 |
| `Adapter\Psr\Log\ObservationLoggerPublisher` | 18 |
| `SDK\Builder\RunItBuilder` | 13 |
| `Adapter\CloudEvents\Mapping\DefaultCloudEventToOccurrenceMapper` | 13 |
| `Application\Publication\BoundedArtifactPublisher` | 12 |
| `Adapter\Common\JsonSnapshotSerializer` | 12 |
| `Domain\Shared\SecurityLimits` | 12 |
| `Adapter\CloudEvents\CloudEventPublisher` | 11 |
| `Adapter\CloudEvents\Serialization\DefaultCloudEventJsonSerializer` | 11 |

The generated class report does not identify each offending method, so the requirement to enumerate **every method** above 10 remains unverified. Prioritize complex security and mapping paths for targeted decomposition and branch tests rather than assuming that a high score proves a defect.

`vendor/bin/phpa src -f xml -o /tmp/kilo/runit-phpa.xml` ran PHP Assumptions 0.9.1 and reported 109 assumption entries among 491 boolean expressions (22%); some entries are duplicates. These are heuristic prompts, not 109 confirmed bugs. For example, the null check in `src/Adapter/Swoole/SwooleRuntimeDetector.php:32` reflects a legitimate Fiber runtime distinction, and the token-identity checks in `src/Adapter/Fiber/FiberContextStorage.php:60-68` enforce isolation and LIFO behavior. Review such branches for missing negative tests instead of removing them mechanically.

## Verification record

| Command | Result |
| --- | --- |
| `composer validate --strict` | Passed. |
| `composer architecture` | Passed, 395 analyzed classes; subject to rule-scope limitation above. |
| `composer lint:length` | Passed, with configured exclusions above. |
| `composer api:check` | Failed: one public interface compatibility break. |
| `composer analyse` | Failed: 137 PHPStan errors. |
| `composer cs:check` | Failed: two test files. |
| `composer audit` | After the dependency update: no security vulnerability advisories; one abandoned package, `doctrine/annotations`. |
| `composer why doctrine/annotations --tree` | Development dependency via `just-bases/php-qa-base` and `phpbench/phpbench`. |
| `composer audit --no-dev` | Earlier review run: no security vulnerability advisories found; not rerun after the dependency update. |
| `composer test -- --no-colors` | Passed; the final concurrency suite reported 4 tests and 81 assertions. |
| `composer coverage` | Passed as a command; 813 tests, 5,677 assertions, 88.18% unit statement coverage. |
| `vendor/bin/phpmetrics ... src` | Completed; class and method maxima above. |
| `vendor/bin/phpa src -f xml -o /tmp/kilo/runit-phpa.xml` | Completed; heuristic output above. |

The unit coverage command selects Unit (`composer.json:90`), whereas the ordinary test command invokes suites separately (`composer.json:77-87`). This is evidence for suite selection, not proof that individual tests avoid all indirect coverage attribution. PCOV did not provide branch coverage. PHP 8.4, mutation testing, benchmarks, exhaustive per-test coverage isolation, and full dependency exploitability analysis were not run. No application code or API baseline was changed as part of this review.
