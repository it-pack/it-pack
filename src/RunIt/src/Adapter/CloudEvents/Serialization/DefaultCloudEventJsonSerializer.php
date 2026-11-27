<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Serialization;

use CloudEvents\Exceptions\InvalidAttributeException;
use CloudEvents\Serializers\JsonSerializer;
use CloudEvents\Serializers\Normalizers\V1\Denormalizer;
use CloudEvents\Serializers\Normalizers\V1\Normalizer;
use DateTimeImmutable;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventAttributeKey;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSerializerInterface;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use ItPack\RunIt\Contract\Domain\SecurityLimitsInterface;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use JsonException;
use Throwable;
use TypeError;
use ValueError;

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
        CloudEventAttributeKey::SpecVersion->value,
        CloudEventAttributeKey::Id->value,
        CloudEventAttributeKey::Source->value,
        CloudEventAttributeKey::Type->value,
        CloudEventAttributeKey::Subject->value,
        CloudEventAttributeKey::Time->value,
        CloudEventAttributeKey::DataContentType->value,
        CloudEventAttributeKey::DataSchema->value,
        CloudEventAttributeKey::Data->value,
    ];

    public function __construct(
        private CloudEventMappingPolicy $policy = new CloudEventMappingPolicy(),
        private SecurityLimitsInterface $limits = new SecurityLimits(),
    ) {
    }

    /**
     * @throws JsonException
     * @throws InvalidAttributeException
     */
    public function serialize(CloudEvent $event): string
    {
        $fields = $event->toArray();
        // toArray merges extensions last, so inspect collisions before conversion.
        $collision = array_intersect(
            array_keys($event->extensions),
            [...self::STANDARD_FIELDS, CloudEventAttributeKey::DataBase64->value],
        );
        if ($collision !== []) {
            throw new InvalidCloudEventException('CloudEvent extension collides with a reserved field.');
        }

        $json = $this->sdkJson($fields) ?? json_encode(
            $fields,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $bytes = strlen($json);
        if ($bytes > $this->policy->maxSerializedBytes) {
            throw InvalidCloudEventException::eventTooLarge($bytes, $this->policy->maxSerializedBytes);
        }

        return $json;
    }

    /**
     * @throws InvalidAttributeException
     * @throws JsonException
     */
    public function deserialize(string $payload): CloudEvent
    {
        $this->assertWithinSizeLimit($payload, $this->policy->maxSerializedBytes);

        $fields = $this->decodePayload($payload);
        $this->assertRequiredFieldsPresent($fields);
        $this->sdkJson($fields);
        $extensions = $this->extractExtensions($fields);

        $id = $fields[CloudEventAttributeKey::Id->value];
        assert(is_string($id));
        $source = $fields[CloudEventAttributeKey::Source->value];
        assert(is_string($source));
        $type = $fields[CloudEventAttributeKey::Type->value];
        assert(is_string($type));
        $specVersion = $fields[CloudEventAttributeKey::SpecVersion->value];
        assert(is_string($specVersion));

        return CloudEvent::create(
            $id,
            $source,
            $type,
            $fields[CloudEventAttributeKey::Data->value] ?? null,
            $this->optionalString($fields, CloudEventAttributeKey::Subject->value),
            $this->optionalString($fields, CloudEventAttributeKey::Time->value),
            $this->optionalString($fields, CloudEventAttributeKey::DataContentType->value),
            $this->optionalString($fields, CloudEventAttributeKey::DataSchema->value),
            $extensions,
            $specVersion,
        );
    }

    /**
     * Return null only for an explicitly permitted representational incompatibility.
     *
     * @param array<array-key, mixed> $fields
     */
    private function sdkJson(array $fields): ?string
    {
        try {
            $precision = $this->assertSdkRepresentable($fields);
            $sdkEvent = new Denormalizer()->denormalize($fields);
            // Fail on invalid JSON data before the SDK's non-throwing json_encode.
            json_encode($fields, JSON_THROW_ON_ERROR);
            $json = new JsonSerializer(new Normalizer(['subsecondPrecision' => $precision]))
                ->serializeStructured($sdkEvent)
            ;
            $roundTrip = $this->decodePayload($json);
            array_walk_recursive($fields, static function (mixed &$value): void {
                if (is_float($value)) {
                    $encoded = json_encode($value, JSON_THROW_ON_ERROR);
                    $decoded = json_decode($encoded, flags: JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
                    if (is_int($decoded)) {
                        $value = $decoded;
                    }
                }
            });
            ksort($roundTrip);
            ksort($fields);
            if ($roundTrip !== $fields) {
                throw new ValueError('SDK conversion would change the CloudEvent.');
            }

            return $json;
        } catch (InvalidAttributeException $exception) {
            $cause = $exception->getPrevious();
            if (!$cause instanceof ValueError && !$cause instanceof TypeError) {
                throw $exception;
            }
            $failure = $exception;
        } catch (Throwable $exception) {
            $failure = $exception;
        }

        if ($this->policy->allowLegacyFallback) {
            return null;
        }

        throw new InvalidCloudEventException('CloudEvent cannot be represented losslessly by the SDK.', 0, $failure);
    }

    /**
     * @param array<array-key, mixed> $fields
     * @return int<0, 6>
     */
    private function assertSdkRepresentable(array $fields): int
    {
        if (array_key_exists(CloudEventAttributeKey::DataBase64->value, $fields)) {
            throw new ValueError('Unsupported or colliding CloudEvent field.');
        }

        foreach ([
            CloudEventAttributeKey::Subject->value,
            CloudEventAttributeKey::Time->value,
            CloudEventAttributeKey::DataContentType->value,
            CloudEventAttributeKey::DataSchema->value,
        ] as $key) {
            if (array_key_exists($key, $fields) && (!is_string($fields[$key]) || $fields[$key] === '')) {
                throw new ValueError('Unsupported optional CloudEvent field.');
            }
        }

        foreach ($fields as $key => $value) {
            if (is_string($key) && in_array($key, self::STANDARD_FIELDS, true)) {
                continue;
            }
            if (
                !is_string($key)
                || (!is_string($value) && !is_int($value) && !is_bool($value))
                || preg_match('/^[a-z0-9]+$/', $key) !== 1
            ) {
                throw new ValueError('Unsupported CloudEvent extension.');
            }
        }

        $this->assertUriReferences($fields);

        return $this->timestampPrecision($fields[CloudEventAttributeKey::Time->value] ?? null);
    }

    /** @param array<array-key, mixed> $fields */
    private function assertUriReferences(array $fields): void
    {
        foreach ([CloudEventAttributeKey::Source->value, CloudEventAttributeKey::DataSchema->value] as $key) {
            if (isset($fields[$key]) && (!is_string($fields[$key])
                || preg_match('/[\x00-\x20\x7f]|%(?![0-9a-fA-F]{2})/', $fields[$key]) === 1)) {
                throw new ValueError('Invalid URI reference.');
            }
        }
    }

    /** @return int<0, 6> */
    private function timestampPrecision(mixed $time): int
    {
        if ($time === null) {
            return 0;
        }
        $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.(\d{1,6}))?(?:Z|[+-]\d{2}:\d{2})$/';
        if (!is_string($time) || preg_match($pattern, $time, $matches) !== 1) {
            throw new ValueError('Invalid or over-precise timestamp.');
        }
        $format = str_contains($time, '.') ? 'Y-m-d\TH:i:s.uP' : 'Y-m-d\TH:i:sP';
        $date = DateTimeImmutable::createFromFormat($format, $time);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false
            && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))) {
            throw new ValueError('Invalid timestamp.');
        }
        return min(6, strlen($matches[1] ?? ''));
    }

    private function assertWithinSizeLimit(string $payload, int $maxBytes): void
    {
        $bytes = strlen($payload);
        if ($bytes > $maxBytes) {
            throw InvalidCloudEventException::eventTooLarge($bytes, $maxBytes);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $payload): array
    {
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

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function assertRequiredFieldsPresent(array $fields): void
    {
        foreach ([
            CloudEventAttributeKey::SpecVersion->value,
            CloudEventAttributeKey::Id->value,
            CloudEventAttributeKey::Source->value,
            CloudEventAttributeKey::Type->value,
        ] as $required) {
            if (!isset($fields[$required]) || !is_string($fields[$required]) || $fields[$required] === '') {
                throw InvalidCloudEventException::missingField($required);
            }
        }
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function extractExtensions(array $fields): array
    {
        $extensions = [];
        foreach ($fields as $key => $value) {
            if (in_array($key, self::STANDARD_FIELDS, strict: true)) {
                continue;
            }

            $extensions[$key] = $value;
        }

        return $extensions;
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
