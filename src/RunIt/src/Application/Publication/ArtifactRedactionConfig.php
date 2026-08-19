<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

/**
 * Configuration for the default artifact redactor.
 *
 * Redaction is deny-list based: callers name the attribute keys and nested data
 * keys whose values must be masked before external publication, and toggle whether
 * throwable messages, occurrence data, and snapshot payloads are masked. The marker
 * replaces every masked scalar value; arrays are walked recursively so a secret never
 * survives inside a nested payload.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ArtifactRedactionConfig
{
    /**
     * @param list<string> $sensitiveAttributeKeys attribute keys masked in `attributes`
     * @param list<string> $sensitiveDataKeys       keys masked recursively in occurrence `data` and snapshot `payload`
     * @param string       $marker                   value substituted for masked scalars
     */
    public function __construct(
        public array $sensitiveAttributeKeys = [],
        public array $sensitiveDataKeys = [],
        public string $marker = '[redacted]',
        public bool $redactThrowableMessages = true,
        public bool $redactOccurrenceData = true,
        public bool $redactSnapshotPayloads = true,
    ) {
    }

    /**
     * Returns a config that masks nothing.
     */
    public static function disabled(): self
    {
        return new self(
            sensitiveAttributeKeys: [],
            sensitiveDataKeys: [],
            marker: '[redacted]',
            redactThrowableMessages: false,
            redactOccurrenceData: false,
            redactSnapshotPayloads: false,
        );
    }
}
