<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Mapping;

use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventExtensionKey;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventToOccurrenceMapperInterface;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;

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
 * @see https://w3id.org/itpack/runit#CloudEvent
 */
final class DefaultCloudEventToOccurrenceMapper implements CloudEventToOccurrenceMapperInterface
{
    private const string CORRELATION_ATTRIBUTE_KEY = 'correlation.id';

    public function map(CloudEvent $event): \ItPack\RunIt\Contract\Domain\OccurrenceInterface
    {
        $executionIdValue = $event->extensions[CloudEventExtensionKey::ExecutionId->value] ?? null;
        if (!is_string($executionIdValue) || $executionIdValue === '') {
            throw InvalidCloudEventException::missingField(CloudEventExtensionKey::ExecutionId->value);
        }

        $activityIdValue = $event->extensions[CloudEventExtensionKey::ActivityId->value] ?? null;
        $activityId = is_string($activityIdValue) && $activityIdValue !== ''
            ? ActivityId::fromString($activityIdValue)
            : null;

        $sequenceValue = $event->extensions[CloudEventExtensionKey::Sequence->value] ?? null;
        if (is_int($sequenceValue)) {
            $sequence = ArtifactSequence::of($sequenceValue);
        } elseif (is_numeric($sequenceValue)) {
            $sequence = ArtifactSequence::of((int) $sequenceValue);
        } else {
            $sequence = ArtifactSequence::initial();
        }

        $attributes = [];

        $skippedReserved = array_diff(
            CloudEventExtensionKey::knownKeys(),
            [CloudEventExtensionKey::CorrelationId->value],
        );

        foreach ($event->extensions as $key => $value) {
            if (in_array($key, $skippedReserved, true)) {
                continue;
            }

            if ($key === CloudEventExtensionKey::CorrelationId->value && is_string($value)) {
                $attributes[self::CORRELATION_ATTRIBUTE_KEY] = $value;

                continue;
            }

            /** @var mixed $value */
            $attributes[$key] = $value;
        }

        /** @var array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>> $finalAttributes */
        $finalAttributes = $attributes;

        return Occurrence::create(
            OccurrenceId::fromString($event->id),
            \ItPack\RunIt\Domain\Execution\ExecutionId::fromString($executionIdValue),
            OccurrenceType::fromString($event->type),
            $event->source,
            $this->parseTime($event->time),
            $event->data,
            $activityId,
            $event->subject,
            $event->dataSchema,
            $event->dataContentType ?? 'application/json',
            AttributeCollection::fromArray($finalAttributes),
            $sequence,
        );
    }

    private function parseTime(?string $time): Instant
    {
        if ($time === null) {
            return Instant::now();
        }

        try {
            return Instant::of(new \DateTimeImmutable($time)->getTimestamp());
        } catch (\Throwable) {
            throw InvalidCloudEventException::malformedTime($time);
        }
    }
}
