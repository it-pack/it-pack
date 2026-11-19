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
use ItPack\RunIt\Domain\Shared\ArtifactField;
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
        if (!$artifact instanceof RedactedArtifactInterface
            || $artifact->artifactType() !== ArtifactType::OCCURRENCE) {
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
        return !is_string($fields[ArtifactField::ID] ?? null)
            || !is_string($fields[ArtifactField::EXECUTION_ID] ?? null)
            || (!is_string($fields[ArtifactField::ACTIVITY_ID] ?? null)
                && ($fields[ArtifactField::ACTIVITY_ID] ?? null) !== null)
            || !is_int($fields[ArtifactField::TIME] ?? null) || $fields[ArtifactField::TIME] < 0
            || !is_int($fields[ArtifactField::SEQUENCE] ?? null) || $fields[ArtifactField::SEQUENCE] < 0;
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
                    ArtifactField::ID => $fields[ArtifactField::ID],
                    ArtifactField::EXECUTION_ID => $fields[ArtifactField::EXECUTION_ID],
                    ArtifactField::ACTIVITY_ID => $fields[ArtifactField::ACTIVITY_ID] ?? null,
                    ArtifactField::TYPE => 'runit.redacted.v1',
                    ArtifactField::VERSION => '1',
                    ArtifactField::SOURCE => 'runit:redacted',
                    ArtifactField::SUBJECT => null,
                    ArtifactField::TIME => $fields[ArtifactField::TIME],
                    ArtifactField::DATA => null,
                    ArtifactField::DATA_SCHEMA => null,
                    ArtifactField::CONTENT_TYPE => 'application/json',
                    ArtifactField::EXTENSION_ATTRIBUTES => [],
                    ArtifactField::SEQUENCE => $fields[ArtifactField::SEQUENCE],
                ],
            )
        );
    }
}
