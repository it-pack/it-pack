<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Behavior\Application\Publication\DelegatesPublisherLifecycleBehavior;
use ItPack\RunIt\Behavior\Application\Publication\DropsWithHealthReport;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\TypedExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/** @internal Drops measurements without an approved field policy before sampling. */
final class DefaultMeasurementClassifier implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    use DelegatesPublisherLifecycleBehavior;
    use DropsWithHealthReport;

    public function __construct(
        private readonly ArtifactPublisherInterface $inner,
        private readonly ?InternalHealthReporterInterface $health,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        if (
            !$this->isMeasurement($artifact)) {

            $this->inner->publish($artifact);

            return;
        }

        $this->reportDrop('Default measurement classification dropped an unclassified measurement.');
    }

    private function isMeasurement(ExecutionArtifactInterface $artifact): bool
    {
        return $artifact instanceof MeasurementInterface
            || ($artifact instanceof TypedExecutionArtifactInterface && $artifact->artifactType() === 'measurement');
    }
}
