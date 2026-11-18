<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — custom redaction: sensitive keys masked before publication.
 *
 * Phase 2 feature: DefaultArtifactRedactor masks configured sensitive attribute
 * keys and data keys before the artifact reaches the publisher stack. Observation
 * messages are redacted by default.
 *
 * This example shows:
 *   1. ArtifactRedactionConfig names sensitive keys and a replacement marker.
 *   2. DefaultArtifactRedactor masks values matching sensitive keys.
 *   3. Observation messages are redacted by default.
 *
 * Run from the package root: `php examples/redaction-custom.php`
 */

require __DIR__ . '/../vendor/autoload.php';

$publisher = new InMemoryArtifactPublisher();

// --- Redactor masks credit card numbers and user emails ---
$redactor = new DefaultArtifactRedactor(
    new ArtifactRedactionConfig(
        sensitiveAttributeKeys: ['credit_card', 'email'],
        sensitiveDataKeys: ['password', 'token'],
        marker: '[redacted]',
        redactThrowableMessages: true,
        redactOccurrenceData: true,
        redactSnapshotPayloads: true,
        redactObservationMessages: true,
        redactMeasurementValues: false,
    ),
);

$runIt = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withRedactor($redactor)
    ->build()
;

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('operation'),
);

$runIt->run($definition('redacted.op'), static function (ExecutionScopeInterface $scope): void {
    $scope->observations()->record(
        'Processing payment for user alice@example.com',
        ObservationSeverity::Info,
        AttributeCollection::fromArray(['credit_card' => '4111-1111-1111-1111', 'order_id' => '12345']),
    );

    echo "observation recorded with sensitive attributes\n";
    echo "credit_card and email are masked before publication\n";
    echo "observation message is redacted by default\n";
    echo "order_id passes through unredacted\n";
});

echo "redaction complete\n";
