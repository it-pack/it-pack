<?php

declare(strict_types=1);

namespace ItPack\ConfigureItRunit\Tests;

use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Execution\Observation as ConfigureItObservation;
use ItPack\ConfigureIt\Execution\Occurrence as ConfigureItOccurrence;
use ItPack\ConfigureIt\ValueObject\OccurrenceType;
use ItPack\ConfigureIt\ValueObject\Severity;
use ItPack\ConfigureIt\Wiring\Configurations;
use ItPack\ConfigureItRunit\ConfigureItExecutions;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Domain\Occurrence\Occurrence as RunItOccurrence;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end proof of the decoupled contract: a real ConfigureIt resolution
 * runs inside a real RunIt execution, and every emitted fact lands on that
 * execution's recorders — without either package importing the other.
 */
final class ConfigureItExecutionsTest extends TestCase
{
    private InMemoryArtifactPublisher $publisher;
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = __DIR__.'/Support/Fixture/Config';
    }

    protected function tearDown(): void
    {

    }

    private function runtime(): \ItPack\RunIt\SDK\RunItInterface
    {
        return RunItBuilder::create()
            ->withPublisher($this->publisher)
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->build();
    }

    /**
     * @return list<string>
     */
    private function occurrenceTypes(): array
    {
        return array_map(
            static fn (RunItOccurrence $o): string => $o->type()->toString(),
            array_values(array_filter(
                $this->publisher->published(),
                static fn ($artifact): bool => $artifact instanceof RunItOccurrence,
            )),
        );
    }

    private function measurementValue(string $name): int|float
    {
        foreach ($this->publisher->published() as $artifact) {
            if (
                $artifact instanceof MeasurementInterface
                && $artifact->name()->toString() === 'configureit.' . $name
            ) {
                return $artifact->value();
            }
        }

        self::fail('missing measurement ' . $name);
    }

    /**
     * @return list<\ItPack\RunIt\Contract\Domain\SnapshotInterface>
     */
    private function configSnapshots(): array
    {
        return array_values(array_filter(
            $this->publisher->published(),
            static fn ($artifact): bool => $artifact instanceof SnapshotInterface
                && $artifact->type()->toString() === 'configureit.configuration.resolved',
        ));
    }

    public function testDefinitionCarriesNameAndCanonicalKind(): void
    {
        $definition = ConfigureItExecutions::definition('config.resolve');

        self::assertSame('config.resolve', $definition->name()->toString());
        self::assertSame(ConfigureItExecutions::EXECUTION_KIND, $definition->kind()->toString());
        self::assertSame('configuration.resolution', $definition->kind()->toString());
    }

    public function testResolutionInsideExecutionRecordsFullArtifactSet(): void
    {
        $snapshot = ConfigureItExecutions::run(
            $this->runtime(),
            'config.resolve',
            function (ResolutionReporterInterface $reporter) {
                $resolver = Configurations::builder()
                    ->addReader(new FileReader())
                    ->addSource(new FileSource($this->dir))
                    ->addLoader(new PhpLoader())
                    ->addLoader(new JsonLoader())
                    ->build($reporter);

                return $resolver->resolve();
            },
        );

        self::assertInstanceOf(ConfigurationSnapshotInterface::class, $snapshot);

        $types = $this->occurrenceTypes();

        self::assertContains('configureit.configuration.loaded.v1', $types);
        self::assertContains('configureit.configuration.resolved.v1', $types);
        self::assertContains('configureit.configuration.snapshot_created.v1', $types);

        // The finer RunIt activity vocabulary is expanded from the coarse stages.
        // (document-merge and schema-validation complete only when a pipeline
        // processor / schema validate the graph; they are opened regardless.)
        self::assertContains('configureit.activity.file_discovery.finished.v1', $types);
        self::assertContains('configureit.activity.resource_reading.finished.v1', $types);
        self::assertContains('configureit.activity.yaml_loading.finished.v1', $types);
        self::assertContains('configureit.activity.variable_resolution.finished.v1', $types);
        self::assertContains('configureit.activity.graph_building.finished.v1', $types);
        self::assertContains('configureit.activity.snapshot_freezing.finished.v1', $types);

        // Measurements arrive namespaced with their values intact.
        self::assertGreaterThanOrEqual(1, $this->measurementValue('files_loaded'));
        self::assertGreaterThanOrEqual(1, $this->measurementValue('graph_nodes'));
        self::assertGreaterThan(0, $this->measurementValue('duration'));
        self::assertGreaterThan(0, $this->measurementValue('memory'));

        // Exactly one terminal configuration snapshot is produced by the bridge.
        $snapshots = $this->configSnapshots();
        self::assertCount(1, $snapshots);

        $payload = $snapshots[0]->payload();
        self::assertIsArray($payload);
        self::assertArrayHasKey('files_loaded', $payload);
        self::assertArrayHasKey('graph_nodes', $payload);
        self::assertTrue($snapshots[0]->classification()->isTerminal());
    }

    /**
     * Exercises the validated/warnings/snapshot branches directly through a
     * captured reporter so each recorder call is pinned (per the milestone's
     * measurement, observation, and occurrence requirements).
     */
    public function testReporterMapsValidatedStageAndWarnings(): void
    {
        ConfigureItExecutions::run(
            $this->runtime(),
            'config.probe',
            static function (ResolutionReporterInterface $reporter): void {
                $reporter->record(new ConfigureItOccurrence(
                    OccurrenceType::Validated,
                    microtime(true),
                    ['valid' => true, 'errors' => 2, 'warnings' => 1],
                ));
                $reporter->record(new ConfigureItOccurrence(
                    OccurrenceType::Merged,
                    microtime(true),
                    ['processors' => 1],
                ));
                $reporter->observe(new ConfigureItObservation(
                    Severity::Warning,
                    'careful: db.host overridden',
                    ['path' => 'db.host'],
                ));
                $reporter->record(new ConfigureItOccurrence(
                    OccurrenceType::SnapshotCreated,
                    microtime(true),
                ));
            },
        );

        $types = $this->occurrenceTypes();
        self::assertContains('configureit.configuration.merged.v1', $types);
        self::assertContains('configureit.configuration.validated.v1', $types);
        self::assertContains('configureit.activity.document_merge.finished.v1', $types);
        self::assertContains('configureit.activity.schema_validation.finished.v1', $types);
        self::assertContains('configureit.activity.snapshot_freezing.finished.v1', $types);

        self::assertSame(2, $this->measurementValue('validation_errors'));
        self::assertSame(1, $this->measurementValue('validation_warnings'));

        $warnings = array_values(array_filter(
            $this->publisher->published(),
            static fn ($artifact): bool => ($artifact->normalize()['severity'] ?? null) === 'warning',
        ));
        self::assertCount(1, $warnings);
        $warning = $warnings[0]->normalize();
        self::assertSame('[redacted]', $warning['message']);
        self::assertSame('db.host', $warning['attributes']['path']);

        $snapshots = $this->configSnapshots();
        self::assertCount(1, $snapshots);
        self::assertSame(2, $snapshots[0]->payload()['validation_errors']);
        self::assertSame(1, $snapshots[0]->payload()['validation_warnings']);
    }

    /**
     * A clean resolution emits no warnings, proving the bridge does not invent
     * observations the underlying resolution did not produce.
     */
    public function testCleanResolutionEmitsNoObservations(): void
    {
        ConfigureItExecutions::run(
            $this->runtime(),
            'config.clean',
            function (ResolutionReporterInterface $reporter) {
                $resolver = Configurations::builder()
                    ->addReader(new FileReader())
                    ->addSource(new FileSource($this->dir))
                    ->addLoader(new PhpLoader())
                    ->build($reporter);

                return $resolver->resolve();
            },
        );

        $observations = array_values(array_filter(
            $this->publisher->published(),
            static fn ($artifact): bool => isset($artifact->normalize()['severity']),
        ));
        self::assertSame([], $observations);
    }
}
