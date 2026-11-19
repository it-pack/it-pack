<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\PublisherStackFactoryInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — custom factory boundary: classified projections only.
 *
 * Phase 2 feature: The SDK applies its redactor and classification before the
 * custom publisher stack. Custom stack factories receive null for the redactor
 * argument and classified projections — never raw artifacts or business IDs.
 *
 * This example shows:
 *   1. A custom PublisherStackFactory implements the contract.
 *   2. The factory receives null redactor (SDK handles redaction).
 *   3. Artifacts reaching the factory are already classified projections.
 *
 * Run from the package root: `php examples/custom-factory-boundary.php`
 */

require __DIR__ . '/../vendor/autoload.php';

final class ClassifyingStackFactory implements PublisherStackFactoryInterface
{
    public function compose(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
    ): ArtifactPublisherInterface {
        echo 'custom factory: received redactor=' . ($redactor instanceof RedactorInterface ? 'present' : 'null') . "\n";
        echo "custom factory: classified projection will be published through base\n";

        return $base;
    }
}

$basePublisher = new InMemoryArtifactPublisher();

$runIt = RunItBuilder::create()
    ->withPublisher($basePublisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withPublisherStackFactory(new ClassifyingStackFactory())
    ->build()
;

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('operation'),
);

$runIt->run($definition('custom.factory'), static function (ExecutionScopeInterface $scope): void {
    echo "execution reached custom factory boundary\n";
    echo "business IDs never reach the custom factory — only classified projections do.\n";
});

echo "custom factory boundary complete\n";
