<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Application\Factory\RedactedRedactedArtifactFactory;
use ItPack\RunIt\Behavior\Application\Publication\DelegatesPublisherLifecycleBehavior;
use ItPack\RunIt\Behavior\Application\Publication\DropsWithHealthReport;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactedArtifactFactoryInterface;
use ItPack\RunIt\Contract\Domain\ArtifactType;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\TypedExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use Throwable;

/** @internal Classifies verified default-SDK observations before sampling. */
final class DefaultObservationClassifier implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    use DelegatesPublisherLifecycleBehavior;
    use DropsWithHealthReport;

    private const ArtifactType ARTIFACT_TYPE = ArtifactType::Observation;

    private readonly ClassifyingArtifactPublisher $classifier;

    private readonly RedactedArtifactFactoryInterface $redactedFactory;

    public function __construct(
        private readonly ArtifactPublisherInterface $inner,
        private readonly ?InternalHealthReporterInterface $health,
        SecurityLimits $limits,
        ?RedactedArtifactFactoryInterface $redactedFactory = null,
    ) {
        $this->classifier = new ClassifyingArtifactPublisher($inner, $health, $limits);
        $this->redactedFactory =
            $redactedFactory?->withArtifactType(self::ARTIFACT_TYPE)
            ?? new RedactedRedactedArtifactFactory(self::ARTIFACT_TYPE);
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        if (!$artifact instanceof TypedExecutionArtifactInterface
            || $artifact->artifactType() !== ArtifactType::OBSERVATION) {
            $this->inner->publish($artifact);

            return;
        }

        $fields = $artifact->normalize();
        $severityValue = $fields[ArtifactField::SEVERITY] ?? null;
        $severity = is_string($severityValue) ? ObservationSeverity::tryFrom($severityValue) : null;

        if ($severity === null || $this->isNotValid($fields)) {
            $this->reportDrop('Default observation classification failed.');

            return;
        }

        $this->doPublish($fields, $severity);
    }

    /**
     * @throws Throwable
     *
     * @param array<string, mixed> $fields
     */
    private function doPublish(array $fields, ObservationSeverity $severity): void
    {
        $this->classifier->publish(
            $this->redactedFactory->create(
                [
                    ArtifactField::ID => $fields[ArtifactField::ID],
                    ArtifactField::EXECUTION_ID => $fields[ArtifactField::EXECUTION_ID],
                    ArtifactField::ACTIVITY_ID => $fields[ArtifactField::ACTIVITY_ID] ?? null,
                    ArtifactField::SEVERITY => $severity->value,
                    ArtifactField::LEVEL => $severity->level(),
                    ArtifactField::MESSAGE => '[redacted]',
                    ArtifactField::DIAGNOSTIC_CODE => null,
                    ArtifactField::SEQUENCE => $fields[ArtifactField::SEQUENCE],
                    ArtifactField::TIMESTAMP => $fields[ArtifactField::TIMESTAMP],
                    ArtifactField::ATTRIBUTES => [],
                    ArtifactField::THROWABLE => $this->safeThrowable($fields[ArtifactField::THROWABLE] ?? null),
                ],
                ArtifactType::OBSERVATION
            )
        );
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function isNotValid(array $fields): bool
    {
        return !is_string($fields[ArtifactField::ID] ?? null)
            || !is_string($fields[ArtifactField::EXECUTION_ID] ?? null)
            || !is_int($fields[ArtifactField::TIMESTAMP] ?? null) || $fields[ArtifactField::TIMESTAMP] < 0
            || !is_int($fields[ArtifactField::SEQUENCE] ?? null) || $fields[ArtifactField::SEQUENCE] < 0
            || (!is_string($fields[ArtifactField::ACTIVITY_ID] ?? null)
                && ($fields[ArtifactField::ACTIVITY_ID] ?? null) !== null);
    }
}
