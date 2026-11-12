<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

/**
 * Configuration for the default artifact redactor.
 *
 * Redaction is deny-list based: callers name the attribute keys and nested data
 * keys whose values must be masked before external publication, and toggle whether
 * throwable messages, occurrence data, and snapshot payloads are masked. Configured
 * keys are replaced with the marker, including array-valued entries. Traversal is
 * limited to arrays and may truncate data; unconfigured keys and unsupported values
 * are not classified and can contain sensitive information.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ArtifactRedactionConfig
{
    /**
     * @param list<string> $sensitiveAttributeKeys attribute keys masked in `attributes`, occurrence `extension_attributes`, and snapshot `attributes`
     * @param list<string> $sensitiveDataKeys       keys masked recursively in occurrence `data` and snapshot `payload`
     * @param string       $marker                   value substituted for masked values
     * @param bool         $redactThrowableMessages   mask throwable message and trace when throwing; recursive for `previous` causes
     * @param bool         $redactOccurrenceData     mask occurrence `data` recursively when enabled
     * @param bool         $redactSnapshotPayloads   mask snapshot `payload` recursively when enabled
     * @param bool         $redactObservationMessages mask observation `message` when enabled (default true; setting `false` opts out of masking observation messages)
     * @param bool         $redactMeasurementValues  mask measurement `value` when enabled (opt-in, default false)
     */
    public function __construct(
        public array $sensitiveAttributeKeys = [],
        public array $sensitiveDataKeys = [],
        public string $marker = '[redacted]',
        public bool $redactThrowableMessages = true,
        public bool $redactOccurrenceData = true,
        public bool $redactSnapshotPayloads = true,
        public bool $redactObservationMessages = true,
        public bool $redactMeasurementValues = false,
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
            redactObservationMessages: false,
            redactMeasurementValues: false,
        );
    }
}
