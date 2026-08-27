# Third-Party Consumer Walkthrough — `it-pack/run-it-third-party-sample`

This document is written for a reader who has **never seen the RunIt source**. It is the
"documentation reviewed independently" gate item for M14.8: follow it verbatim against a fresh
checkout of this package and record every stumble.

The package is a *separate* Composer package that depends on `it-pack/run-it` only as a vendor
dependency. It proves a third party can:

1. implement `ArtifactPublisherInterface` and `ContextStorageInterface` from scratch,
2. run the **shipped** conformance verifiers against those implementations, and
3. wire a real execution through the public SDK builder.

## 1. Install

```bash
cd src/RunItThirdPartySample
composer install
```

This resolves `it-pack/run-it` (via the path repository in `composer.json`) and the dev tools
(PHPUnit, PHPStan, php-cs-fixer, phpcs).

## 2. Run the conformance suites

```bash
composer test:unit
```

You should see three passing tests:

- `PublisherConformanceTest` — drives `ArtifactPublisherVerifier` against
  `StreamLinePublisher` (minimal) and `BufferedFilePublisher` (flushable + closeable).
- `ContextStorageConformanceTest` — drives `ContextStorageVerifier` against
  `StaticStackContextStorage`.
- `ContextStorageInteropTest` — runs a real execution with `StaticStackContextStorage` as the
  ambient scope storage and asserts the operation sees exactly the attached scope.

None of these tests extend any RunIt base class; they only call the public conformance toolkit
(`ItPack\RunIt\Conformance\*`) and the public SDK builder.

## 3. How a third party gets a scope for context-storage conformance

`ContextStorageVerifier::verify()` needs an `ExecutionScopeInterface`. The contract says the
storage only stores and returns the scope, so its *contents* are irrelevant — but a third party
still needs a real instance.

Building one by hand would mean implementing `ExecutionScopeInterface` plus the six interfaces it
returns (`ExecutionInterface`, `ExecutionContextInterface`, and four recorder interfaces), a deep
stub graph. Instead, a third party obtains a real scope the way any consumer would: by running a
real execution:

```php
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionKind;

$runIt = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$scope = null;
$runIt->run(
    ExecutionDefinition::create(ExecutionName::fromString('demo'), ExecutionKind::fromString('cli.command')),
    static function ($scope) use (&$captured): void { $captured = $scope; },
);
```

`$captured` is a genuine `ExecutionScopeInterface` produced by the public SDK — exactly what you
hand to the verifier alongside your own storage. This is the recommended, consumer-safe pattern
and should be documented in `docs/conformance.md` (see finding TP-001 in `docs/THIRD_PARTY_PLAN.md`).

## 4. Quality gates the sample itself satisfies

```bash
composer validate            # composer.json is valid
composer analyse              # PHPStan on src/
composer cs:check             # php-cs-fixer dry run
composer cs:lines             # phpcs line-length (120)
```

## 5. What to record

If anything above fails, or any step is unclear, open an entry in the findings ledger in
`docs/THIRD_PARTY_PLAN.md` (columns: `id`, `area`, `severity`, `observed`, `suggested fix`,
`status`). Critical findings (a third party cannot implement a port, run its conformance suite,
or follow this walkthrough) must be resolved before the `1.0.0` freeze.
