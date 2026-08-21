<?php

declare(strict_types=1);

/**
 * RunIt third-party adapter conformance example.
 *
 * Shows how an external adapter author reuses the framework-agnostic verifiers
 * without inheriting from any test framework. You implement the port; the shipped
 * fixture and verifiers do the rest. The pattern works identically under PHPUnit,
 * Pest, Codeception, or a plain PHP script — only the assertion/exit glue differs.
 *
 * This file is intentionally dependency-free apart from RunIt itself. The two
 * integration points a third party owns are:
 *
 *   1. The adapter (here: a trivial in-memory collector behind the port).
 *   2. Optionally a fixture that builds artifacts from the author's own domain
 *      objects, when they are not RunIt domain objects.
 *
 * Run from the package root: `php examples/conformance/third-party-adapter.php`
 */

require __DIR__ . '/../../vendor/autoload.php';

use ItPack\RunIt\Conformance\Contract\PortFixtureInterface;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\ArtifactPublisherVerifier;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierValidatorInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;

/**
 * A hypothetical third-party publisher. It implements only the port interface;
 * nothing else in RunIt is required to write or verify it.
 */
final class ExampleVendorPublisher implements ArtifactPublisherInterface
{
    /** @var list<ExecutionArtifactInterface> */
    private array $sent = [];

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $this->sent[] = $artifact;
    }

    /**
     * @return list<ExecutionArtifactInterface>
     */
    public function sent(): array
    {
        return $this->sent;
    }
}

/**
 * A third-party fixture that reuses RunIt's artifact material (so the author does
 * not need to rebuild observations/measurements) while leaving room to substitute
 * their own domain objects for any method. Implementing {@see PortFixtureInterface}
 * is the only contract the verifier depends on.
 */
final readonly class ExampleVendorFixture implements PortFixtureInterface
{
    public function __construct(private PortFixtureInterface $inner = new RunItPortFixture())
    {
    }

    public function safeObservation(): ObservationInterface
    {
        return $this->inner->safeObservation();
    }

    public function sensitiveObservation(): ObservationInterface
    {
        return $this->inner->sensitiveObservation();
    }

    public function sensitiveAttributeValue(): string
    {
        return $this->inner->sensitiveAttributeValue();
    }

    public function measurement(): MeasurementInterface
    {
        return $this->inner->measurement();
    }

    public function occurrence(): OccurrenceInterface
    {
        return $this->inner->occurrence();
    }

    public function snapshot(): SnapshotInterface
    {
        return $this->inner->snapshot();
    }

    /**
     * @return Closure(SnapshotInterface): SnapshotInterface
     */
    public function snapshotPatcher(): Closure
    {
        return $this->inner->snapshotPatcher();
    }

    public function stringIdentifierValidator(): StringIdentifierValidatorInterface
    {
        return $this->inner->stringIdentifierValidator();
    }

    /**
     * @return list<ExecutionArtifactInterface>
     */
    public function samplingArtifacts(): array
    {
        return $this->inner->samplingArtifacts();
    }
}

$publisher = new ExampleVendorPublisher();
$fixture = new ExampleVendorFixture();

$result = new ArtifactPublisherVerifier()->verify($publisher, $fixture);

if (!$result->isConformant()) {
    fwrite(STDERR, "Third-party publisher is NOT conformant:\n");
    foreach ($result->violations as $violation) {
        fwrite(STDERR, (string) $violation . PHP_EOL);
    }
    exit(1);
}

// A conformant publisher actually received every artifact it was given.
assert(count($publisher->sent()) >= 4);

echo sprintf("Third-party ExampleVendorPublisher received %d artifacts and passed the ArtifactPublisherInterface conformance suite.\n", count($publisher->sent()));

echo "Third-party ExampleVendorPublisher passed the ArtifactPublisherInterface conformance suite.\n";
exit(0);
