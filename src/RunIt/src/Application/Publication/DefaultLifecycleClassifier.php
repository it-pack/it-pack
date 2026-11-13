<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Application\Factory\RedactedRedactedArtifactFactory;
use ItPack\RunIt\Behavior\Application\Publication\DelegatesPublisherLifecycleBehavior;
use ItPack\RunIt\Behavior\Application\Publication\DropsWithHealthReport;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactedArtifactFactoryInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
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

    private const string ARTIFACT_TYPE = 'unknown';

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
        if ($kind !== 'execution' && $kind !== 'activity') {
            $this->inner->publish($artifact);
            return;
        }

        $fields = $artifact->normalize();
        $statusValue = $fields['status'] ?? null;
        $status = is_string($statusValue) ? $this->getStatus($statusValue, $kind) : null;
        $parent = $this->getParent($kind);

        if ($status === null || !$this->validFields($fields, $kind, $parent)) {
            $this->reportDrop('Default lifecycle classification failed.');
            return;
        }

        $safe = $this->getSafeFields($fields, $status, $parent);

        if ($kind === 'execution') {
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
            !is_string($fields['id'] ?? null)
            || ($kind === 'activity' && !is_string($fields['execution_id'] ?? null))
            || (!is_string($fields[$parent] ?? null) && ($fields[$parent] ?? null) !== null)
            || !is_int($fields['started_at'] ?? null) || $fields['started_at'] < 0
            || !is_int($fields['sequence'] ?? null) || $fields['sequence'] < 0
            || (isset($fields['ended_at']) && (!is_int($fields['ended_at']) || $fields['ended_at'] < 0))
            || (isset($fields['duration_ns']) && (!is_int($fields['duration_ns']) || $fields['duration_ns'] < 0))
            || ($kind === 'execution' && (!is_array($fields['linked_execution_ids'] ?? null)
            || !array_is_list($fields['linked_execution_ids'])
            || count($fields['linked_execution_ids']) > $this->limits->maxAttributeCount
            || array_any($fields['linked_execution_ids'], static fn (mixed $id): bool => !is_string($id))))
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function getSafeFields(array $fields, ActivityStatus|ExecutionStatus $status, string $parent): array
    {
        return [
            'id' => $fields['id'],
            'name' => 'runit.redacted',
            'kind' => 'runit.redacted',
            'status' => $status->value,
            'started_at' => $fields['started_at'],
            'ended_at' => $fields['ended_at'] ?? null,
            $parent => $fields[$parent] ?? null,
            'sequence' => $fields['sequence'],
        ];
    }

    private function getStatus(string $status1, string $kind): null|ExecutionStatus|ActivityStatus
    {
        return $kind === 'execution'
            ? ExecutionStatus::tryFrom($status1)
            : ActivityStatus::tryFrom($status1);
    }

    private function getParent(string $kind): string
    {
        return $kind === 'execution' ? 'parent_execution_id' : 'parent_activity_id';
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
        $safe['linked_execution_ids'] = $fields['linked_execution_ids'];
        $safe['context'] = [];
        $result = $fields['result'] ?? null;
        $safe['result'] = $status->isTerminal() ? [
            'status' => $status->value,
            'throwable' => $status === ExecutionStatus::Succeeded || !is_array($result)
                ? null : $this->safeThrowable($result['throwable'] ?? null, 12),
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
        $safe['execution_id'] = $fields['execution_id'];
        $safe['attributes'] = [];
        $safe['throwable'] = $this->safeThrowable($fields['throwable'] ?? null, 12);
        if (isset($fields['duration_ns'])) {
            $safe['duration_ns'] = $fields['duration_ns'];
        }

        return $safe;
    }
}
