<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\ClassifiedOccurrenceFactoryInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use Throwable;

/** @internal Reconstructs occurrences exclusively from classified values. */
final class ClassifiedOccurrenceFactory implements ClassifiedOccurrenceFactoryInterface
{
    /** @param array<string, mixed> $fields */
    public function create(array $fields): ?OccurrenceInterface
    {
        foreach ([
            ArtifactField::ID,
            ArtifactField::EXECUTION_ID,
            ArtifactField::TYPE,
            ArtifactField::SOURCE,
            ArtifactField::TIME,
            ArtifactField::DATA,
            ArtifactField::CONTENT_TYPE,
            ArtifactField::EXTENSION_ATTRIBUTES,
            ArtifactField::SEQUENCE,
            ArtifactField::ACTIVITY_ID,
            ArtifactField::SUBJECT,
            ArtifactField::DATA_SCHEMA,
            ArtifactField::VERSION,
        ] as $key) {
            if (!array_key_exists($key, $fields)) {
                return null;
            }
        }

        if (!is_string($fields[ArtifactField::ID]) || !is_string($fields[ArtifactField::EXECUTION_ID])
            || !is_string($fields[ArtifactField::TYPE]) || !is_string($fields[ArtifactField::SOURCE])
            || !is_int($fields[ArtifactField::TIME]) || !is_string($fields[ArtifactField::CONTENT_TYPE])
            || !is_array($fields[ArtifactField::EXTENSION_ATTRIBUTES]) || !is_int($fields[ArtifactField::SEQUENCE])
            || (!is_string($fields[ArtifactField::ACTIVITY_ID]) && $fields[ArtifactField::ACTIVITY_ID] !== null)
            || (!is_string($fields[ArtifactField::SUBJECT]) && $fields[ArtifactField::SUBJECT] !== null)
            || (!is_string($fields[ArtifactField::DATA_SCHEMA]) && $fields[ArtifactField::DATA_SCHEMA] !== null)) {
            return null;
        }

        try {
            $occurrence = Occurrence::create(
                OccurrenceId::fromString($fields[ArtifactField::ID]),
                ExecutionId::fromString($fields[ArtifactField::EXECUTION_ID]),
                OccurrenceType::fromString($fields[ArtifactField::TYPE]),
                $fields[ArtifactField::SOURCE],
                Instant::of($fields[ArtifactField::TIME]),
                $fields[ArtifactField::DATA],
                $fields[ArtifactField::ACTIVITY_ID] === null
                    ? null
                    : ActivityId::fromString($fields[ArtifactField::ACTIVITY_ID]),
                $fields[ArtifactField::SUBJECT],
                $fields[ArtifactField::DATA_SCHEMA],
                $fields[ArtifactField::CONTENT_TYPE],
                AttributeCollection::fromArray(
                    $this->typedExtensionAttributes($fields[ArtifactField::EXTENSION_ATTRIBUTES])
                ),
                ArtifactSequence::of($fields[ArtifactField::SEQUENCE]),
            );

            // Reject defaults or conversions that would invent projected values.
            $normalized = $occurrence->normalize();
            ksort($normalized);
            ksort($fields);

            return $normalized === $fields ? $occurrence : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Builds a string-keyed, primitive-bounded attribute map from the raw,
     * caller-controlled `extension_attributes` of a normalized occurrence.
     * Non-string keys and non-primitive values are dropped; `AttributeCollection`
     * validates the result on construction.
     *
     * @param array<mixed, mixed> $attributes
     * @return array<string, int|float|string|bool|null|array<int|string, mixed>>
     */
    private function typedExtensionAttributes(array $attributes): array
    {
        $result = [];
        foreach ($attributes as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
