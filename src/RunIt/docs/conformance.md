# Port Conformance Verification

RunIt ships a framework-agnostic conformance toolkit so that **any** port
implementation — first-party or third-party — can be verified against the same
behavioral contract. A third-party adapter author reuses the shipped verifiers
and supplies only their adapter (and, optionally, a fixture). No verifier extends
a test-framework base class, so the same checks run unchanged under PHPUnit,
Pest, Codeception, or a plain PHP script.

The toolkit lives in `src/Conformance/`:

```text
src/Conformance/
├── Contract/PortFixtureInterface.php  # the single contract a verifier depends on
├── Fixture/RunItPortFixture.php       # bundled fixture for the first-party ports
├── Verifier/                          # one verifier per port contract
└── Result/                            # ConformanceResult / ConformanceViolation / ViolationSeverity
```

## Verifiers

| Verifier | Port contract verified |
|---|---|
| `ArtifactPublisherVerifier` | `ArtifactPublisherInterface` — publish, ordering, recursion guard |
| `ContextStorageVerifier` | `ContextStorageInterface` — LIFO attach/detach, current, invalid token |
| `IdentifierGeneratorVerifier` | `IdentifierGeneratorInterface` — uniqueness, format |
| `MonotonicClockVerifier` | `MonotonicClockInterface` — monotonic ordering, wall/mono separation |
| `SerializerVerifier` | `NormalizerInterface` / serializer port — determinism |
| `RedactorVerifier` | `RedactorInterface` — secret/confidential masking |
| `SamplingPolicyVerifier` | `SamplingPolicyInterface` — decision stability |
| `SnapshotStoreVerifier` | `SnapshotStoreInterface` — persistence, predecessor chains |
| `ExecutionEngineVerifier` | `ExecutionEngineInterface` — lifecycle, exception preservation |

Each verifier exposes `verify(port, fixture): ConformanceResult`. The result model
is independent of any test runner:

- `ConformanceResult::isConformant(): bool`
- `ConformanceResult::$violations`: `list<ConformanceViolation>`
- `ConformanceViolation` carries a `ViolationSeverity` (`error` | `warning`) and a
  human-readable message. Verifiers treat behavioral contract breaches as
  `error` severity; advisory checks are `warning`.

## The fixture contract

A verifier needs artifact material (observations, measurements, occurrences,
snapshots, identifiers). It obtains that material exclusively through
`PortFixtureInterface`. The bundled `RunItPortFixture` builds that material from
RunIt's own domain objects, so a first-party adapter needs no custom fixture.

A third-party author may:

1. Reuse `RunItPortFixture` directly when their adapter consumes RunIt artifacts.
2. Decorate `RunItPortFixture` and substitute only the methods that involve their
   own domain objects (see `examples/conformance/third-party-adapter.php`).
3. Implement `PortFixtureInterface` from scratch when none of RunIt's artifacts
   apply.

Implementing `PortFixtureInterface` is the **only** contract a verifier depends
on; nothing else in RunIt is required to write or verify an adapter.

## Run it as a plain script (no test framework)

`examples/conformance/third-party-adapter.php` is a complete, dependency-free
example. The pattern is:

```php
require __DIR__ . '/../../vendor/autoload.php';

use ItPack\RunIt\Conformance\Verifier\ArtifactPublisherVerifier;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;

$adapter = new MyVendorPublisher();           // implements the port interface
$fixture = new RunItPortFixture();            // or your own PortFixtureInterface

$result = (new ArtifactPublisherVerifier())->verify($adapter, $fixture);

if (!$result->isConformant()) {
    fwrite(STDERR, "Adapter is NOT conformant:\n");
    foreach ($result->violations as $violation) {
        fwrite(STDERR, (string) $violation . PHP_EOL);
    }
    exit(1);
}

exit(0);
```

Run it from the package root:

```bash
php examples/conformance/third-party-adapter.php
```

## Run it inside a test framework

The verifiers return plain result objects, so they drop into any framework's
assertion layer. Under PHPUnit/Pest/Codeception:

```php
$result = (new ArtifactPublisherVerifier())->verify($adapter, $fixture);

self::assertTrue($result->isConformant(), (string) $result);
```

This package wires the first-party adapters into a Codeception suite so they are
verified on every run:

```bash
vendor/bin/codecept run Conformance
```

`tests/Conformance/RunItPortConformanceTest.php` drives every verifier against
the bundled adapters through `RunItPortFixture`. That suite is the in-repo
evidence for the "first-party adapters pass their conformance suites" gate.

## Authoring a new verifier

A new port verifier is a plain class that:

- depends only on the port contract and `PortFixtureInterface`;
- returns a `ConformanceResult` (never throws on a behavioral mismatch);
- reports contract breaches as `error` and advisory notes as `warning`;
- does not extend a framework base class.

Add the verifier to `src/Conformance/Verifier/` and, for first-party ports, a
case in `tests/Conformance/RunItPortConformanceTest.php`.

## Reference

- Verifiers and fixture: `src/Conformance/`
- First-party conformance suite: `tests/Conformance/RunItPortConformanceTest.php`
- Third-party example: `examples/conformance/third-party-adapter.php`
- Bundled runner: `examples/conformance/run-conformance.php`
