<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Exception\InvalidAttributeException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — security limits bounding artifact data.
 *
 * Phase 2 feature: SecurityLimits bounds attribute count, key/value bytes,
 * depth, occurrence payload, snapshot payload, serialization depth/bytes, and
 * tracked executions. Payloads exceeding limits are dropped or replaced with
 * a payload-free marker before they can leave the process.
 *
 * This example shows:
 *   1. SecurityLimits configured with tight bounds.
 *   2. An oversized attribute value raises InvalidAttributeException on construction.
 *   3. An attribute within the limit is accepted and recorded.
 *   4. Limits are fail-closed: oversized data never reaches external sinks.
 *
 * Run from the package root: `php examples/security-limits.php`
 */

require __DIR__ . '/../vendor/autoload.php';

$publisher = new InMemoryArtifactPublisher();

// --- Tight limits: max 4 attributes, 16 bytes per value ---
$limits = SecurityLimits::default()
    ->withMaxAttributeCount(4)
    ->withMaxAttributeValueBytes(16)
    ->withMaxSnapshotPayloadBytes(256)
    ->withMaxOccurrencePayloadBytes(128)
;

$runIt = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withSecurityLimits($limits)
    ->build()
;

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('operation'),
);

$runIt->run($definition('bounded.op'), static function (ExecutionScopeInterface $scope) use ($limits): void {
    // Attribute within limit — successful construction control.
    try {
        $attrs = AttributeCollection::fromArray(['ok' => 'short'], limits: $limits);
        $scope->observations()->record(
            'within limit',
            ObservationSeverity::Info,
            $attrs,
        );
        echo 'within-limit attribute accepted' . "\n";
    } catch (InvalidAttributeException $invalidAttributeException) {
        echo 'within-limit attribute unexpectedly rejected: ' . $invalidAttributeException->getMessage() . "\n";
    }

    // Attribute exceeding 16-byte value limit — rejected.
    try {
        $attrs = AttributeCollection::fromArray(['too_long' => 'this-value-is-way-too-long'], limits: $limits);
        $scope->observations()->record(
            'oversized value',
            ObservationSeverity::Info,
            $attrs,
        );
        echo 'oversized attribute unexpectedly accepted' . "\n";
    } catch (InvalidAttributeException $invalidAttributeException) {
        echo 'oversized attribute rejected: ' . $invalidAttributeException->getMessage() . "\n";
    }

    echo 'security limits active:' . "\n";
    echo '  max attribute count:   ' . $limits->maxAttributeCount . "\n";
    echo '  max attribute value:   ' . $limits->maxAttributeValueBytes . ' bytes' . "\n";
    echo '  max snapshot payload:  ' . $limits->maxSnapshotPayloadBytes . ' bytes' . "\n";
    echo '  max occurrence payload:' . $limits->maxOccurrencePayloadBytes . ' bytes' . "\n";
    echo 'oversized attributes are rejected with InvalidAttributeException' . "\n";
    echo 'limits are fail-closed — nothing oversized reaches external sinks' . "\n";
});

echo 'security limits complete' . "\n";
