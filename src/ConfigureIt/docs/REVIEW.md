# ConfigureIt Project Review

Review date: 2026-11-11

## Scope and assumptions

This review covers the ConfigureIt package, including its production code, tests,
package tooling, and relevant monorepo CI. Findings describe the current checkout,
not an assessment of every possible deployment or external dependency. Severity
reflects impact and preconditions, not proof that a production incident occurred.
Cache-related findings apply when the relevant optional cache is enabled.

The requested conventions are evaluated as review criteria, not silently applied
as new repository policy. In particular, the request prohibits Yoda comparisons,
requires all construction in factories/builders and puts vocabularies in
interfaces; `AGENTS.md` instead mandates Yoda comparisons, permits direct creation
of immutable data, and places single-use vocabulary in owning classes. The
request's declaration order also differs from the configured public-static-first
order. Resolve these policy conflicts explicitly before changing the code or
automated checks; avoid an unreviewed breaking API migration.

## Summary

The package has a substantial, interface-oriented resolution pipeline, pluggable
sources, loaders and processors, provenance, schema validation, typed access,
optional caching, and adapters for remote systems. The most consequential risks
are cache identity and invalidation: unrelated documents can collide, and cached
snapshots can bypass changes in precedence, source content and validation rules.
Coverage exceeds the aggregate threshold but includes incidental collaborator and
integration execution, so direct unit coverage is not established.

## Findings

### High: Installed dependency graph has known advisories

`composer security:audit` reports advisories in `symfony/config`
([GHSA-5jff-8h8q-r5qx](https://github.com/symfony/symfony/security/advisories/GHSA-5jff-8h8q-r5qx),
XML local-file disclosure), `symfony/filesystem`
([GHSA-6prc-h956-hxjq](https://github.com/symfony/symfony/security/advisories/GHSA-6prc-h956-hxjq),
path escape), and `symfony/yaml`
([GHSA-hwfg-qwvh-2wfx](https://github.com/symfony/symfony/security/advisories/GHSA-hwfg-qwvh-2wfx),
parser denial of service). The installed graph includes development tooling;
`composer why` also identifies `webuni/front-matter` as a YAML consumer. Installed
versions are Config 8.1.10, Filesystem 8.1.9, and YAML 8.1.10. The package lists
that adapter dependency under
`require-dev` (`composer.json:21-31`),
so downstream exposure depends on the dependencies and inputs consumers actually
install and use. Audit does not establish exploitability in this package. Update
within supported constraints, re-run the audit and tests, and assess untrusted input
paths. The audit also reports abandoned `doctrine/annotations`.

### High: Compiled cache keys do not identify resolution behavior

`src/Core/ConfigurationCompiler.php:51-82` sorts source descriptors even though
the pipeline explicitly gives later documents higher precedence
(`src/Core/ConfigurationPipeline.php:19-23`). Reversing sources can reuse the
wrong winner. Non-file resources are keyed by URI without a content version;
remote or environment changes, including revoked credentials, can remain invisible
indefinitely on a compiled-cache hit (`src/Adapter/EnvSource.php:23-34`,
`src/Adapter/EnvReader.php:37-48`). Schema fingerprints include only name and optional
scalar types (`ConfigurationCompiler.php:88-100`); a tighter validation constraint
can reuse the previous artifact, bypassing validation on the hit
(`src/Core/CachingResolver.php:70-76`). Processor, reader and loader identities
are only class names, not their configuration, and other options are not fully
represented. Existing file-change coverage does not establish these cases
(`tests/Unit/Core/CompilerCacheTest.php:56-67`).

Preserve source order and require explicit stable fingerprints for all relevant
source versions, schema rules, collaborators and resolution policies. If reliable
fingerprints are unavailable, bypass compiled caching or use a documented bounded
lifetime and revalidation. Add regression tests for reversed sources, stable-URI
updates, changed schema constraints and configured collaborators.

### High: Document-cache filename collisions mix resources

`src/Adapter/CachingReader.php:75-77` builds a key from format and URI, but
`src/Cache/FileSystemDocumentCache.php:106-110` replaces every disallowed
character with `_`. Consequently `https://example.test/a/b` and
`https://example.test/a_b` collide for the same format. A fresh hit can return
another resource's configuration, including across tenants sharing a cache.
`tests/Unit/Cache/FileSystemDocumentCacheTest.php:247-255` demonstrates the
underlying collision. Use a collision-resistant digest of the entire logical key,
including any needed tenant or authorization identity, and cover both distinct
URI and shared-cache isolation cases.

### Medium: Cache failures and one-shot sources break resolution

`src/Core/CachingResolver.php:57-76` hashes an iterable and then hands the same
iterable to the inner resolver. A generator is exhausted on a cache miss; an empty
generator also does not select the same effective defaults as the inner resolver
(`src/Core/ConfigurationResolver.php:212-219`). Hashing and cache reads occur
outside the write-only exception handler, contrary to the documented fallback
(`CachingResolver.php:24-30,61-82`). Both filesystem cache implementations include
PHP artifacts without catching parse errors (`src/Cache/FileSystemCache.php:58`,
`src/Cache/FileSystemDocumentCache.php:44,73`). Materialize inputs once, select
effective sources consistently, and make cache failures fall back without hiding
business-resolution errors. Test empty and nonempty generators, throwing reads,
and syntactically corrupt artifacts.

### Medium: Remote transport errors bypass last-good fallback

`src/Adapter/CachingReader.php:53-63` catches `ReadException` only, whereas
`src/Adapter/Enterprise/HttpReader.php:42` lets client exceptions propagate and
`src/Adapter/Enterprise/Http/CurlHttpClient.php:58-60` raises
`RuntimeException` on transport failure. A populated stale entry therefore does
not protect against that failure mode. Translate transport exceptions at adapter
boundaries, preserve their causes, and exercise actual adapter failure paths in
last-good tests (`tests/Unit/Adapter/Enterprise/RemoteSourceTest.php:375-399`).

### Medium: Cache artifacts require a trusted, private directory

Both filesystem caches execute their stored artifacts using `include` and reuse
existing directories without checking ownership or permissions
(`src/Cache/FileSystemCache.php:41-58`,
`src/Cache/FileSystemDocumentCache.php:29-44`). Writes use process umask
(`FileSystemCache.php:101-106`, `FileSystemDocumentCache.php:84-96`), and
artifacts may contain decrypted configuration. A traversable existing directory
can expose files; a party able to write artifacts can execute PHP as the
application. This is conditional on deployment permissions, not a claim of
unauthenticated remote execution. Validate a private trusted directory, enforce
restrictive file permissions, and consider a non-executable data format. Test
pre-existing permissive directories and resulting file modes.

### Medium: Wiring and snapshot semantics can surprise consumers

`ConfigurationBuilder::withWiring()` returns a new builder with only factory and
wiring (`src/Core/ConfigurationBuilder.php:179-192`), silently discarding prior
sources, schema, processors and options despite documenting use on an existing
builder. Preserve existing state or make the restriction explicit and reject
unsafe usage. `src/Core/ConfigurationSnapshot.php:24-34,73-76,148-154` stores and
returns heterogeneous values without freezing contained objects; PHP loader
results can contain objects (`src/Adapter/PhpLoader.php:50-59`). Readonly arrays
do not make those objects immutable. Define and enforce the supported value model
or document the shallow guarantee and provide a safe isolation strategy.

### Medium: Coverage and quality gates do not establish the requested assurance

The combined run reaches 97.35% statements, but `CachingResolverTest` declares
`@covers CachingResolver` (`tests/Unit/Core/CachingResolverTest.php:21`) and its
isolated report credits four production collaborators as well. Integration tests
also run real collaborators, so combined execution coverage is not direct unit
coverage. A strict-annotation command-line override produced identical results;
whether that override was actually applied was not established. Configure and
verify coverage metadata and separate direct unit coverage from integration
coverage, then enforce the intended threshold on the intended denominator.

The gate treats zero statements/elements as 100%
(`scripts/coverage_check.php:58-62`), allowing an empty parseable report to
pass. Reject empty or incomplete reports. `composer.json:46-50,61-68` runs only
Unit in `test` and `quality`, and does not include coverage; the shared CI test
step uses `composer test` (`../../.github/workflows/quality.yml:97-98`). Add
explicit integration and fresh coverage generation/checking to the desired CI
gate. Method coverage is 91.51%, below 95%; the current gate intentionally checks
statements and elements, not methods, and no branch coverage was measured.

### Medium: Layer and dependency rules are weaker than advertised

`phparkitect.php:45-55` allows Core to depend on Cache and Execution and Adapter
to depend on Core. The configured check passes, but cannot enforce the stricter
package guidance. Injectable collaborators are also default-constructed, for
example `src/Core/ConfigurationPipeline.php:37-41`,
`src/Core/ConfigurationBuilder.php:77-80`, and `src/Adapter/JsonLoader.php:24`.
The constructor-default guard enumerates only selected files
(`tests/Unit/Wiring/NoInjectableConstructorDefaultsTest.php:33-64`). Distinguish
services from data and composition roots, expand guards to the agreed policy,
and migrate with public constructor compatibility in mind. The package explicitly
documents adapter-private and CLI/codegen exceptions to interface-backed ports
(`docs/PORTS.md:33-50`); these are not proof that every DI service has an
interface. The schema `getAdaptee(): object` escape hatch
(`src/Contract/SchemaInterface.php:39-44`, `src/Contract/ContextInterface.php:22-27`)
also leaks adapter mechanics and permits incompatible pairings at runtime.

### Low: Optional adapter installation is a consumer responsibility

The manifest requires only PHP at runtime while libraries used by optional
adapters are in `require-dev` (`composer.json:6-8,21-31`). Consumers must install
the relevant vendor package themselves, as the TOML loader's availability check
illustrates (`src/Adapter/TomlLoader.php:41-45`). Keep installation instructions
and missing-dependency diagnostics explicit for each adapter; avoid implying a
development install proves all optional adapters work in a consumer installation.

### Low: Null-TTL cache entries are never fresh

The remote cache contract says null TTL never expires
(`src/Contract/RemoteDocumentCacheInterface.php:37-42`), but `isset` rejects a
stored null expiry (`src/Cache/FileSystemDocumentCache.php:44-47,89-92`). This
causes unnecessary backend traffic for default-TTL callers. Distinguish missing
from present-null expiry and correct the contradictory test
(`tests/Unit/Cache/FileSystemDocumentCacheTest.php:150-159`).

## Duplication and maintainability

- Scalar conversion acceptance logic is repeated in `src/Core/ScalarCaster.php:37-98`
  and `src/Core/CastingProcessor.php:99-160`. Share the conversion policy while
  preserving their different exception types and messages.
- Nette private-property reflection is repeated in
  `src/Adapter/NativeConfigurationSchema.php:130-147` and
  `src/Codegen/SchemaModelBuilder.php:225-242`. Centralize the vendor-specific
  mechanism behind an appropriate adapter boundary; mappings overlap but have
  different output shapes and should not be merged mechanically.
- Scalar type vocabulary is repeated in `src/Core/CastingProcessor.php:90-95`,
  `src/Core/TypedSnapshot.php:154-159`, and
  `src/Adapter/NativeConfigurationSchema.php:118-123`. Give shared vocabulary
  one contract home while preserving serialized/public string values.
- Repeated construction of immutable value objects is not automatically harmful
  duplication; apply the stricter requested factory rule only after settling the
  conflict with the package's explicit data-object carve-out.

## Metrics and conventions

PhpMetrics 2.11.0 analyzed `src/`: 133 classes, 64 interfaces, 503 methods,
6,900 LOC, and average class cyclomatic complexity 6.11. It reported 0 critical,
12 error, and 20 warning violations. The following classes have a reported maximum
method cyclomatic complexity above 10; these are review candidates, not proof
that the entire class is defective. PhpMetrics' CSV reports a per-class maximum.

| Class | Maximum method CCN | Class CCN |
| --- | ---: | ---: |
| `ValueObject\Provenance` | 22 | 42 |
| `Adapter\Enterprise\Client\ConsulClient` | 20 | 27 |
| `Adapter\CommandRunner\NativeCommandRunner` | 20 | 26 |
| `Adapter\Enterprise\Client\GcpSecretManagerClient` | 15 | 16 |
| `Core\ConfigurationDiffer` | 14 | 19 |
| `Adapter\Enterprise\Client\EtcdClient` | 13 | 29 |
| `ValueObject\ConfigurationDiff` | 12 | 14 |
| `Adapter\XmlLoader` | 11 | 16 |
| `Cache\FileSystemCache` | 11 | 17 |
| `ValueObject\FlagDefinition` | 11 | 34 |

Method-level attribution was reconstructed using PHP-Parser and PhpMetrics'
decision-node rules; the maximum matched PhpMetrics for each of the ten classes.
The additional `Provenance::copy()` is above the threshold but not its class's
maximum.

| Method | Declaration | CCN |
| --- | --- | ---: |
| `Provenance::fromArray()` | `src/ValueObject/Provenance.php:48` | 22 |
| `ConsulClient::fetch()` | `src/Adapter/Enterprise/Client/ConsulClient.php:49` | 20 |
| `NativeCommandRunner::drive()` | `src/Adapter/CommandRunner/NativeCommandRunner.php:99` | 20 |
| `GcpSecretManagerClient::fetch()` | `src/Adapter/Enterprise/Client/GcpSecretManagerClient.php:33` | 15 |
| `ConfigurationDiffer::summarize()` | `src/Core/ConfigurationDiffer.php:76` | 14 |
| `EtcdClient::fetch()` | `src/Adapter/Enterprise/Client/EtcdClient.php:47` | 13 |
| `Provenance::copy()` | `src/ValueObject/Provenance.php:235` | 13 |
| `ConfigurationDiff::summary()` | `src/ValueObject/ConfigurationDiff.php:111` | 12 |
| `XmlLoader::toArray()` | `src/Adapter/XmlLoader.php:96` | 11 |
| `FileSystemCache::get()` | `src/Cache/FileSystemCache.php:50` | 11 |
| `FlagDefinition::fromArray()` | `src/ValueObject/FlagDefinition.php:66` | 11 |

Additional outliers worth contextual inspection: `ConfigurationBuilder` has 216
LOC, `ConfigurationResolver` has 18 efferent dependencies, and `TempFileFactory`
has maintainability index 56.06. Metrics are heuristics, not refactoring mandates.
PHPAssumptions 0.9.1 reports 65 assumptions in 466 boolean expressions (14%).
These include ordinary explicit comparisons, such as
`src/Adapter/Enterprise/Client/GcpSecretManagerClient.php:54`, and must not be
reported as 65 confirmed defects. XML output is available from the command below.
No actionable assumption was verified in the examples inspected; that is not
proof that all 65 are harmless.

No textual abstract-class declarations were found in production or handwritten
tests. One production `else` occurs in
`src/Adapter/CommandRunner/NativeCommandRunner.php:162`; tests also contain
branches. The configured fixer actively enforces Yoda comparisons, conflicting
with this review request; its one failure is the non-Yoda comparison at
`src/Core/ConfigurationBuilder.php:196`. Its declaration-order check follows the
repository's order, not the requested order, so a pass does not establish the
latter. Imports are not exhaustive: `src/Core/Key.php:44-54` uses global functions
without `use function` imports. A declaration scan found additional named symbols
in six handwritten test files, including `tests/Unit/Adapter/JsonLoaderTest.php:13,46`;
fixtures and generated code need separate handling. Production line-length check
passes; a separate scan found 36 lines over 120 characters in handwritten tests
and 88 in generated support. These scans are not a formal semantic proof of all
imports, services, literals, or declaration ordering.

## Modern PHP and design

PHP-format configuration is executable code: `src/Adapter/PhpLoader.php:43-50`
writes the payload to a temporary file and requires it. Treat PHP configuration
sources as trusted code, not untrusted data, and document that deployment boundary;
this is inherent in the format rather than a claim of an unintended execution path.

The PHP 8.5 baseline already permits readonly classes, enums and typed contracts.
Use readonly for genuinely immutable services and data, but it does not deep-freeze
object graphs. Fibers are useful only if asynchronous adapters have a defined
concurrency, cancellation and context model; they do not accelerate synchronous
I/O automatically. Weak maps/references are appropriate only for identity-bound
ephemeral caches with measured retention problems. Lazy objects may reduce costly
optional-adapter construction if profiling shows startup overhead, but should not
hide failure or introduce shared mutable state. Property hooks can clarify derived
accessors but do not replace explicit ports. Traits can share genuinely cohesive
mechanics; composition is preferable where a collaborator needs substitution.
No evidence here justifies introducing these features merely for novelty.

## Verification

- PHP 8.5.12, PCOV 1.0.12; `composer validate --no-check-publish` passed.
- `composer coverage`: 892 tests, 1,773 assertions, one skipped optional-package
  absence test. `composer coverage:check`: statements 2,719/2,793 (97.35%),
  elements 3,247/3,370 (96.35%), methods 528/577 (91.51%). The combined XML
  covers 203 source files, matching the production PHP file count; PCOV reports
  executable statements, not physical lines, and zero conditional metrics.
- `composer test:unit` passed independently: 871 tests, 1,715 assertions, one
  skipped. `composer test:integration` passed: 21 tests, 58 assertions.
- Isolated `vendor/bin/codecept run Unit Core/CachingResolverTest.php --no-rebuild
  --coverage --coverage-xml=attribution.xml --disable-coverage-php --no-colors`
  passed 3 tests and credited `ConfigurationCompiler` 16/30 lines and three
  further collaborators, in addition to the subject. The isolated executable-line
  denominator was 2,796 rather than 2,793; the cause was not established.
- `composer architecture`, `composer analyse`, `composer lint:length`, and
  `composer lint:magic` passed. The last command ignores the configured values
  2, 100, 200, and 300 and does not establish the absence of magic strings.
  `composer cs:check` failed on the comparison described above; `composer quality`
  consequently stopped at its first step. Mutation was not run separately.
- `composer security:audit` failed with three advisory-bearing packages and one
  abandoned package, as detailed above; this concerns the installed dependency
  graph, not proof that every advisory reaches production. `composer audit
  --no-dev` reported no packages to audit in this checkout; consumers may install
  optional dependencies separately.
- `vendor/bin/phpmetrics --composer=false --report-csv=/tmp/kilo/configureit-phpmetrics.csv
  --report-summary-json=/tmp/kilo/configureit-phpmetrics-summary.json src` completed.
  `vendor/bin/phpa src -f xml -o /tmp/kilo/configureit-phpa.xml` completed. Reports
  in `/tmp/kilo` are temporary, not versioned review artifacts.

This is a prioritized, evidence-backed review rather than an exhaustive proof of
security, direct test coverage, or compliance of every symbol. No public API or
production behavior was changed by this review.
