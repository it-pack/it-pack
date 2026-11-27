<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Mapping;

use DateTimeImmutable;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventExtensionKey;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventToOccurrenceMapperInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;
use ItPack\RunIt\Contract\Shared\CorrelationKeys;
use ItPack\RunIt\Contract\Shared\MediaTypes;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use Throwable;

/**
 * Default CloudEvent-to-occurrence mapper.
 *
 * The mapper rebuilds a RunIt occurrence from a CloudEvent. The required
 * CloudEvent fields are assumed present (the value object and the serializer
 * enforce them), but the RunIt execution identity is mandatory: without the
 * `executionid` extension the event cannot be reconstructed as a RunIt
 * occurrence and is rejected. Known RunIt extension attributes are folded into
 * their semantic fields; every other extension attribute is preserved in the
 * reconstructed occurrence so round trips keep their data.
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 * @see https://cloudevents.io/
 */
final class DefaultCloudEventToOccurrenceMapper implements CloudEventToOccurrenceMapperInterface
{
    public function map(CloudEvent $event): OccurrenceInterface
    {
        $executionId = $this->resolveExecutionId($event->extensions);
        $activityId = $this->resolveActivityId($event->extensions);
        $sequence = $this->resolveSequence($event->extensions);
        $attributes = $this->collectAttributes($event);

        return Occurrence::create(
            OccurrenceId::fromString($event->id),
            ExecutionId::fromString($executionId),
            OccurrenceType::fromString($event->type),
            $event->source,
            $this->parseTime($event->time),
            $event->data,
            $activityId,
            $event->subject,
            $event->dataSchema,
            $event->dataContentType ?? MediaTypes::APPLICATION_JSON,
            AttributeCollection::fromArray($attributes),
            $sequence,
        );
    }

    /**
     * @param array<array-key, mixed> $extensions
     */
    private function resolveExecutionId(array $extensions): string
    {
        $executionIdValue = $extensions[CloudEventExtensionKey::ExecutionId->value] ?? null;
        if (!is_string($executionIdValue) || $executionIdValue === '') {
            throw InvalidCloudEventException::missingField(CloudEventExtensionKey::ExecutionId->value);
        }

        return $executionIdValue;
    }

    /**
     * @param array<array-key, mixed> $extensions
     */
    private function resolveActivityId(array $extensions): ?ActivityId
    {
        $activityIdValue = $extensions[CloudEventExtensionKey::ActivityId->value] ?? null;
        return is_string($activityIdValue) && $activityIdValue !== ''
            ? ActivityId::fromString($activityIdValue)
            : null;
    }

    /**
     * @param array<array-key, mixed> $extensions
     */
    private function resolveSequence(array $extensions): ArtifactSequence
    {
        $sequenceValue = $extensions[CloudEventExtensionKey::Sequence->value] ?? null;
        return match (true) {
            is_int($sequenceValue) => ArtifactSequence::of($sequenceValue),
            is_numeric($sequenceValue) => ArtifactSequence::of((int) $sequenceValue),
            default => ArtifactSequence::initial(),
        };
    }

    /**
     * @return array<string, int|float|string|bool|null|array<int|string, mixed>>
     */
    private function collectAttributes(CloudEvent $event): array
    {
        $attributes = [];

        $skippedReserved = array_diff(
            CloudEventExtensionKey::knownKeys(),
            [CloudEventExtensionKey::CorrelationId->value],
        );

        foreach ($event->extensions as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (in_array($key, $skippedReserved, strict: true)) {
                continue;
            }

            if ($key === CloudEventExtensionKey::CorrelationId->value && is_string($value)) {
                $attributes[CorrelationKeys::CLOUDEVENTS_CORRELATION_ATTRIBUTE] = $value;

                continue;
            }

            if (is_scalar($value) || $value === null || is_array($value)) {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    private function parseTime(?string $time): Instant
    {
        if ($time === null) {
            return Instant::now();
        }

        try {
            return Instant::of(new DateTimeImmutable($time)->getTimestamp());
        } catch (Throwable) {
            throw InvalidCloudEventException::malformedTime($time);
        }
    }
}
