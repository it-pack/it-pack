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
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use Throwable;

/** @internal Classifies verified default-SDK occurrences before sampling. */
final class DefaultOccurrenceClassifier implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    use DelegatesPublisherLifecycleBehavior;
    use DropsWithHealthReport;

    private const ArtifactType ARTIFACT_TYPE = ArtifactType::Occurrence;

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
        if ((!$artifact instanceof RedactedArtifactInterface && !$artifact instanceof VerifiedArtifactProjection)
            || $artifact->artifactType() !== 'occurrence') {
            $this->inner->publish($artifact);
            return;
        }

        $fields = $artifact->normalize();
        if ($this->isNotValid($fields)) {
            $this->reportDrop('Default occurrence classification failed.');

            return;
        }

        $this->doPublish($fields);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function isNotValid(array $fields): bool
    {
        return !is_string($fields['id'] ?? null)
            || !is_string($fields['execution_id'] ?? null)
            || (!is_string($fields['activity_id'] ?? null) && ($fields['activity_id'] ?? null) !== null)
            || !is_int($fields['time'] ?? null) || $fields['time'] < 0
            || !is_int($fields['sequence'] ?? null) || $fields['sequence'] < 0;
    }

    /**
     * @param array<string, mixed> $fields
     * @throws Throwable
     */
    private function doPublish(array $fields): void
    {
        $this->classifier->publish(
            $this->redactedFactory->create(
                [
                    'id' => $fields['id'],
                    'execution_id' => $fields['execution_id'],
                    'activity_id' => $fields['activity_id'] ?? null,
                    'type' => 'runit.redacted.v1',
                    'version' => '1',
                    'source' => 'runit:redacted',
                    'subject' => null,
                    'time' => $fields['time'],
                    'data' => null,
                    'data_schema' => null,
                    'content_type' => 'application/json',
                    'extension_attributes' => [],
                    'sequence' => $fields['sequence'],
                ],
            )
        );
    }
}
