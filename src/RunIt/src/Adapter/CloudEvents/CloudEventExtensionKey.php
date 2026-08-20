<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents;

/**
 * CloudEvents extension attribute keys reserved by the RunIt bridge.
 *
 * CloudEvents extension names must be lower-case letters, digits, and optional
 * hyphens, so the RunIt correlation fields are flattened into single-token keys
 * rather than dotted names. Unknown extension attributes are preserved verbatim
 * when their key is a valid CloudEvents extension name.
 *
 * @see https://w3id.org/itpack/runit#CloudEvent
 */
enum CloudEventExtensionKey: string
{
    case ExecutionId = 'executionid';
    case ActivityId = 'activityid';
    case CorrelationId = 'correlationid';
    case Sequence = 'sequence';

    /**
     * @return list<string>
     */
    public static function knownKeys(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
