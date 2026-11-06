<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Mapping;

use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventExtensionKey;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventFactoryInterface;
use ItPack\RunIt\Adapter\CloudEvents\Contract\OccurrenceToCloudEventMapperInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Contract\Shared\CorrelationKeys;

/**
 * Default occurrence-to-CloudEvent mapper.
 *
 * The mapper translates the RunIt occurrence semantics to the CloudEvents
 * envelope: identity, type, source, subject, time, data, schema, and content
 * type become the standard attributes, while RunIt correlation (execution id,
 * activity id, sequence, correlation id) becomes extension attributes. Custom
 * occurrence extension attributes whose keys are valid CloudEvents extension
 * names are preserved; keys that cannot be represented as CloudEvents extension
 * names are dropped because the spec cannot carry them.
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 * @see https://cloudevents.io/
 */
final readonly class DefaultOccurrenceToCloudEventMapper implements OccurrenceToCloudEventMapperInterface
{
    public function __construct(
        private CloudEventFactoryInterface $factory = new DefaultCloudEventFactory(),
    ) {
    }

    public function map(
        OccurrenceInterface $occurrence,
        ?CloudEventMappingPolicy $policy = null,
    ): CloudEvent {
        $policy ??= CloudEventMappingPolicy::default();

        $extensions = [
            CloudEventExtensionKey::ExecutionId->value => $occurrence->executionId()->toString(),
        ];

        $activityId = $occurrence->activityId();
        if ($activityId instanceof StringIdentifierInterface) {
            $extensions[CloudEventExtensionKey::ActivityId->value] = $activityId->toString();
        }

        $extensions[CloudEventExtensionKey::Sequence->value] = $occurrence->sequence();

        $correlationId = $occurrence->extensionAttributes()[CorrelationKeys::CLOUDEVENTS_CORRELATION_ATTRIBUTE] ?? null;
        if (is_string($correlationId)) {
            $extensions[CloudEventExtensionKey::CorrelationId->value] = $correlationId;
        }

        foreach ($occurrence->extensionAttributes() as $key => $value) {
            if ($key === CorrelationKeys::CLOUDEVENTS_CORRELATION_ATTRIBUTE) {
                continue;
            }

            if (!CloudEvent::isValidExtensionKey($key)) {
                continue;
            }

            $extensions[$key] = $value;
        }

        return $this->factory->create(
            $occurrence->id()->toString(),
            $occurrence->source(),
            $occurrence->type()->toString(),
            $occurrence->data(),
            $occurrence->subject(),
            $this->formatTime($occurrence->time()->epochSeconds()),
            $occurrence->contentType(),
            $occurrence->dataSchema(),
            $extensions,
            $policy->specVersion,
        );
    }

    private function formatTime(int $epochSeconds): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $epochSeconds);
    }
}
