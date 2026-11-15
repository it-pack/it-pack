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
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use Throwable;

/** @internal Classifies authority-verified lifecycle artifacts before sampling. */
final class DefaultLifecycleClassifier implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    use DelegatesPublisherLifecycleBehavior;
    use DropsWithHealthReport;

    private const string ARTIFACT_TYPE = ArtifactType::UNKNOWN;

    private readonly ClassifyingArtifactPublisher $classifier;

    private readonly RedactedArtifactFactoryInterface $redactedFactory;

    public function __construct(
        private readonly ArtifactPublisherInterface $inner,
        private readonly ?InternalHealthReporterInterface $health,
        private readonly SecurityLimits $limits,
        ?RedactedArtifactFactoryInterface $redactedFactory = null,
    ) {
        $this->classifier = new ClassifyingArtifactPublisher($inner, $health, $limits);
        $this->redactedFactory =
            $redactedFactory?->withArtifactType(self::ARTIFACT_TYPE)
            ?? new RedactedRedactedArtifactFactory(self::ARTIFACT_TYPE);
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        if (!$artifact instanceof RedactedArtifactInterface && !$artifact instanceof VerifiedArtifactProjection) {
            $this->inner->publish($artifact);
            return;
        }

        $kind = $artifact->artifactType();
        if ($kind !== ArtifactType::EXECUTION && $kind !== ArtifactType::ACTIVITY) {
            $this->inner->publish($artifact);
            return;
        }

        $fields = $artifact->normalize();
        $statusValue = $fields[ArtifactField::STATUS] ?? null;
        $status = is_string($statusValue) ? $this->getStatus($statusValue, $kind) : null;
        $parent = $this->getParent($kind);

        if ($status === null || !$this->validFields($fields, $kind, $parent)) {
            $this->reportDrop('Default lifecycle classification failed.');
            return;
        }

        $safe = $this->getSafeFields($fields, $status, $parent);

        if ($kind === ArtifactType::EXECUTION) {
            $this->extractExecution($fields, $safe, $status, $kind);
            return;
        }

        $this->classifier->publish(
            $this->redactedFactory->create(
                $this->getSafeDefault($fields, $safe),
                $kind
            )
        );
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function validFields(array $fields, string $kind, string $parent): bool
    {
        return !(
            !is_string($fields[ArtifactField::ID] ?? null)
            || ($kind === ArtifactType::ACTIVITY && !is_string($fields[ArtifactField::EXECUTION_ID] ?? null))
            || (!is_string($fields[$parent] ?? null) && ($fields[$parent] ?? null) !== null)
            || !is_int($fields[ArtifactField::STARTED_AT] ?? null) || $fields[ArtifactField::STARTED_AT] < 0
            || !is_int($fields[ArtifactField::SEQUENCE] ?? null) || $fields[ArtifactField::SEQUENCE] < 0
            || (isset($fields[ArtifactField::ENDED_AT])
                && (!is_int($fields[ArtifactField::ENDED_AT]) || $fields[ArtifactField::ENDED_AT] < 0))
            || (isset($fields[ArtifactField::DURATION_NS])
                && (!is_int($fields[ArtifactField::DURATION_NS]) || $fields[ArtifactField::DURATION_NS] < 0))
            || ($kind === ArtifactType::EXECUTION && (!is_array($fields[ArtifactField::LINKED_EXECUTION_IDS] ?? null)
            || !array_is_list($fields[ArtifactField::LINKED_EXECUTION_IDS])
            || count($fields[ArtifactField::LINKED_EXECUTION_IDS]) > $this->limits->maxAttributeCount
            || array_any($fields[ArtifactField::LINKED_EXECUTION_IDS], static fn (mixed $id): bool => !is_string($id))))
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function getSafeFields(array $fields, ActivityStatus|ExecutionStatus $status, string $parent): array
    {
        return [
            ArtifactField::ID => $fields[ArtifactField::ID],
            ArtifactField::NAME => 'runit.redacted',
            ArtifactField::KIND => 'runit.redacted',
            ArtifactField::STATUS => $status->value,
            ArtifactField::STARTED_AT => $fields[ArtifactField::STARTED_AT],
            ArtifactField::ENDED_AT => $fields[ArtifactField::ENDED_AT] ?? null,
            $parent => $fields[$parent] ?? null,
            ArtifactField::SEQUENCE => $fields[ArtifactField::SEQUENCE],
        ];
    }

    private function getStatus(string $status1, string $kind): null|ExecutionStatus|ActivityStatus
    {
        return $kind === ArtifactType::EXECUTION
            ? ExecutionStatus::tryFrom($status1)
            : ActivityStatus::tryFrom($status1);
    }

    private function getParent(string $kind): string
    {
        return $kind === ArtifactType::EXECUTION
            ? ArtifactField::PARENT_EXECUTION_ID : ArtifactField::PARENT_ACTIVITY_ID;
    }

    /**
     * @throws Throwable
     *
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $safe
     */
    private function extractExecution(
        array $fields,
        array $safe,
        ActivityStatus|ExecutionStatus $status,
        string $kind
    ): void {
        $safe[ArtifactField::LINKED_EXECUTION_IDS] = $fields[ArtifactField::LINKED_EXECUTION_IDS];
        $safe[ArtifactField::CONTEXT] = [];
        $result = $fields[ArtifactField::RESULT] ?? null;
        $safe[ArtifactField::RESULT] = $status->isTerminal() ? [
            ArtifactField::STATUS => $status->value,
            ArtifactField::THROWABLE => $status === ExecutionStatus::Succeeded || !is_array($result)
                ? null : $this->safeThrowable($result[ArtifactField::THROWABLE] ?? null, 12),
        ] : null;

        $this->classifier->publish($this->redactedFactory->create($safe, $kind));
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $safe
     * @return array<string, mixed>
     */
    private function getSafeDefault(array $fields, array $safe): array
    {
        $safe[ArtifactField::EXECUTION_ID] = $fields[ArtifactField::EXECUTION_ID];
        $safe[ArtifactField::ATTRIBUTES] = [];
        $safe[ArtifactField::THROWABLE] = $this->safeThrowable($fields[ArtifactField::THROWABLE] ?? null, 12);
        if (isset($fields[ArtifactField::DURATION_NS])) {
            $safe[ArtifactField::DURATION_NS] = $fields[ArtifactField::DURATION_NS];
        }

        return $safe;
    }
}
