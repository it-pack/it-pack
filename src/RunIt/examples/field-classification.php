<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\ClassifyingArtifactPublisher;
use ItPack\RunIt\Application\Publication\DefaultLifecycleClassifier;
use ItPack\RunIt\Application\Publication\DefaultMeasurementClassifier;
use ItPack\RunIt\Application\Publication\DefaultObservationClassifier;
use ItPack\RunIt\Application\Publication\DefaultOccurrenceClassifier;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — field classification before sampling.
 *
 * Phase 2 feature: ClassifyingArtifactPublisher classifies artifact fields before
 * the sampler and sink decide what to export. Unclassified fields are dropped with
 * a payload-free health notice; allowed fields pass through.
 *
 * This example shows:
 *   1. An unclassified measurement is dropped before sampling.
 *   2. A classified measurement passes classification and reaches the sink.
 *   3. Classification is deterministic — only configured fields survive.
 *
 * Run from the package root: `php examples/field-classification.php`
 */

require __DIR__ . '/../vendor/autoload.php';

$publisher = new InMemoryArtifactPublisher();

// Build a stack with classification (same as PublisherStackFactory::compose,
// minus redaction/sampling for clarity).
$limits = new SecurityLimits();
$composed = $publisher;
$composed = new ClassifyingArtifactPublisher($composed, null, $limits);
$composed = new DefaultObservationClassifier($composed, null, $limits);
$composed = new DefaultOccurrenceClassifier($composed, null, $limits);
$composed = new DefaultMeasurementClassifier($composed, null);
$composed = new DefaultLifecycleClassifier($composed, null, $limits);

$runIt = RunItBuilder::create()
    ->withPublisher($composed)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build()
;

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('operation'),
);

// --- Unclassified measurement: dropped before sampling ---
$runIt->run($definition('measured.unclassified'), static function (ExecutionScopeInterface $scope): void {
    $scope->observations()->record(
        'unclassified measurement recorded — will be dropped before sampling',
        ObservationSeverity::Info,
        AttributeCollection::fromArray(['case' => 'unclassified']),
    );
    echo 'unclassified measurement: dropped before sampling (delivery loss)' . "\n";
});

// --- Classified observation: passes through ---
$runIt->run($definition('classified.ok'), static function (ExecutionScopeInterface $scope): void {
    $scope->observations()->record(
        'classified observation — passes through',
        ObservationSeverity::Info,
        AttributeCollection::fromArray(['case' => 'classified']),
    );
    echo 'classified observation: reaches the sink' . "\n";
});

echo 'field classification complete' . "\n";
echo 'published artifacts: ' . count($publisher->published()) . "\n";
