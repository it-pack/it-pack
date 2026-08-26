<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents;

use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;
use ItPack\RunIt\Contract\Exception\InvalidContextValueException;
use ItPack\RunIt\Domain\Context\ContextValueValidator;

/**
 * An immutable CloudEvent in the structured (JSON) representation.
 *
 * The value object models the CloudEvents 1.0 specification envelope: the
 * required `specversion`, `id`, `source`, and `type`, the optional `subject`,
 * `time`, `datacontenttype`, `dataschema`, and `data`, and any extension
 * attributes carried as sibling keys. Extension attribute keys must be valid
 * CloudEvents extension names (lower-case letters, digits, and optional
 * hyphens); the data payload must be recursively primitive.
 *
 * The object is immutable: every field is validated once, on construction.
 * Serialization to and from the structured JSON form lives in the dedicated
 * serializer, not in this value object, so the event stays a pure data carrier.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#CloudEvent
 */
final readonly class CloudEvent
{
    public const string SPEC_VERSION_1_0 = '1.0';

    private const string TIME_PATTERN = '/^\d{4}-\d{2}-\d{2}[Tt]\d{2}:\d{2}:\d{2}(\.\d+)?([Zz]|[+-]\d{2}:\d{2})$/';

    private const string EXTENSION_KEY_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private function __construct(
        public string $specVersion,
        public string $id,
        public string $source,
        public string $type,
        public mixed $data,
        public ?string $subject,
        public ?string $time,
        public ?string $dataContentType,
        public ?string $dataSchema,
        public array $extensions,
    ) {
    }

    public static function create(
        string $id,
        string $source,
        string $type,
        mixed $data = null,
        ?string $subject = null,
        ?string $time = null,
        ?string $dataContentType = null,
        ?string $dataSchema = null,
        array $extensions = [],
        string $specVersion = self::SPEC_VERSION_1_0,
    ): self {
        if ($specVersion === '') {
            throw InvalidCloudEventException::emptyField('specversion');
        }

        if ($id === '') {
            throw InvalidCloudEventException::emptyField('id');
        }

        if ($source === '') {
            throw InvalidCloudEventException::emptyField('source');
        }

        if ($type === '') {
            throw InvalidCloudEventException::emptyField('type');
        }

        if ($time !== null && preg_match(self::TIME_PATTERN, $time) !== 1) {
            throw InvalidCloudEventException::malformedTime($time);
        }

        try {
            $validator = new ContextValueValidator();
            $validator->assertSupportedValue($data);

            $normalizedExtensions = [];
            foreach ($extensions as $key => $value) {
                if (preg_match(self::EXTENSION_KEY_PATTERN, $key) !== 1) {
                    throw InvalidCloudEventException::unsupportedExtensionKey($key);
                }

                $validator->assertSupportedValue($value);

                $normalizedExtensions[$key] = $value;
            }
        } catch (InvalidContextValueException $e) {
            throw new InvalidCloudEventException($e->getMessage(), 0, $e);
        }

        return new self(
            $specVersion,
            $id,
            $source,
            $type,
            $data,
            $subject,
            $time,
            $dataContentType,
            $dataSchema,
            $normalizedExtensions,
        );
    }

    public function withExtensions(array $extensions): self
    {
        return new self(
            $this->specVersion,
            $this->id,
            $this->source,
            $this->type,
            $this->data,
            $this->subject,
            $this->time,
            $this->dataContentType,
            $this->dataSchema,
            [...$this->extensions, ...$extensions],
        );
    }

    public function toArray(): array
    {
        $event = [
            'specversion' => $this->specVersion,
            'id' => $this->id,
            'source' => $this->source,
            'type' => $this->type,
        ];

        if ($this->subject !== null) {
            $event['subject'] = $this->subject;
        }

        if ($this->time !== null) {
            $event['time'] = $this->time;
        }

        if ($this->dataContentType !== null) {
            $event['datacontenttype'] = $this->dataContentType;
        }

        if ($this->dataSchema !== null) {
            $event['dataschema'] = $this->dataSchema;
        }

        if ($this->data !== null) {
            $event['data'] = $this->data;
        }

        foreach ($this->extensions as $key => $value) {
            /** @var mixed $value */
            $event[$key] = $value;
        }

        return $event;
    }

    public static function isValidExtensionKey(string $key): bool
    {
        return preg_match(self::EXTENSION_KEY_PATTERN, $key) === 1;
    }
}
