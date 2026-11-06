<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Serialization;

use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSerializerInterface;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use JsonException;

/**
 * Structured JSON serializer for CloudEvents.
 *
 * The structured content mode places the CloudEvents attributes and any
 * extension attributes as sibling keys in a single JSON object. Serialization
 * enforces the configured maximum size and rejects payloads that exceed it
 * before decoding, so a hostile or accidental oversized payload fails closed
 * rather than being buffered. Malformed JSON or a missing required field raises
 * a typed exception instead of being silently coerced.
 *
 * @see https://cloudevents.io/
 */
final readonly class DefaultCloudEventJsonSerializer implements CloudEventSerializerInterface
{
    public const string CONTENT_TYPE = 'application/cloudevents+json';

    private const array STANDARD_FIELDS = [
        CloudEvent::SPEC_VERSION_KEY,
        'id',
        'source',
        'type',
        'subject',
        'time',
        'datacontenttype',
        'dataschema',
        'data',
    ];

    public function __construct(
        private CloudEventMappingPolicy $policy = new CloudEventMappingPolicy(),
        private SecurityLimits $limits = new SecurityLimits(),
    ) {
    }

    /**
     * @throws JsonException
     */
    public function serialize(CloudEvent $event): string
    {
        $json = json_encode(
            $event->toArray(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        $bytes = strlen($json);
        if ($bytes > $this->policy->maxSerializedBytes) {
            throw InvalidCloudEventException::eventTooLarge($bytes, $this->policy->maxSerializedBytes);
        }

        return $json;
    }

    public function deserialize(string $payload): CloudEvent
    {
        $bytes = strlen($payload);
        if ($bytes > $this->policy->maxSerializedBytes) {
            throw InvalidCloudEventException::eventTooLarge($bytes, $this->policy->maxSerializedBytes);
        }

        try {
            /** @var positive-int $depth */
            $depth = $this->limits->maxSerializationDepth;
            $decoded = json_decode(
                $payload,
                associative: true,
                depth: $depth,
                flags: JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw InvalidCloudEventException::malformedJson();
        }

        if (!is_array($decoded)) {
            throw InvalidCloudEventException::malformedJson();
        }

        /** @var array<string, mixed> $fields */
        $fields = $decoded;

        foreach ([CloudEvent::SPEC_VERSION_KEY, 'id', 'source', 'type'] as $required) {
            if (!isset($fields[$required]) || !is_string($fields[$required]) || $fields[$required] === '') {
                throw InvalidCloudEventException::missingField($required);
            }
        }

        $extensions = [];
        foreach ($fields as $key => $value) {
            if (in_array($key, self::STANDARD_FIELDS, strict: true)) {
                continue;
            }

            $extensions[$key] = $value;
        }

        return CloudEvent::create(
            $fields['id'],
            $fields['source'],
            $fields['type'],
            $fields['data'] ?? null,
            $this->optionalString($fields, 'subject'),
            $this->optionalString($fields, 'time'),
            $this->optionalString($fields, 'datacontenttype'),
            $this->optionalString($fields, 'dataschema'),
            $extensions,
            $fields[CloudEvent::SPEC_VERSION_KEY],
        );
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function optionalString(array $decoded, string $key): ?string
    {
        if (!isset($decoded[$key])) {
            return null;
        }

        $value = $decoded[$key];

        return is_string($value) && $value !== '' ? $value : null;
    }
}
