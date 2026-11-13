<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use Throwable;

/** @internal Reconstructs occurrences exclusively from classified values. */
final class ClassifiedOccurrenceFactory
{
    /** @param array<string, mixed> $fields */
    public function create(array $fields): ?OccurrenceInterface
    {
        foreach (['id', 'execution_id', 'type', 'source', 'time', 'data', 'content_type',
            'extension_attributes', 'sequence', 'activity_id', 'subject', 'data_schema', 'version'] as $key) {
            if (!array_key_exists($key, $fields)) {
                return null;
            }
        }

        if (!is_string($fields['id']) || !is_string($fields['execution_id'])
            || !is_string($fields['type']) || !is_string($fields['source'])
            || !is_int($fields['time']) || !is_string($fields['content_type'])
            || !is_array($fields['extension_attributes']) || !is_int($fields['sequence'])
            || (!is_string($fields['activity_id']) && $fields['activity_id'] !== null)
            || (!is_string($fields['subject']) && $fields['subject'] !== null)
            || (!is_string($fields['data_schema']) && $fields['data_schema'] !== null)) {
            return null;
        }

        try {
            $occurrence = Occurrence::create(
                OccurrenceId::fromString($fields['id']),
                ExecutionId::fromString($fields['execution_id']),
                OccurrenceType::fromString($fields['type']),
                $fields['source'],
                Instant::of($fields['time']),
                $fields['data'],
                $fields['activity_id'] === null ? null : ActivityId::fromString($fields['activity_id']),
                $fields['subject'],
                $fields['data_schema'],
                $fields['content_type'],
                AttributeCollection::fromArray($fields['extension_attributes']),
                ArtifactSequence::of($fields['sequence']),
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
}
