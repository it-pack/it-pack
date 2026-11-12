<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Telemetry;

/**
 * Stable OpenTelemetry attribute values emitted by the RunIt bridge.
 *
 * Most attribute values are caller-controlled artifact data; this interface
 * holds only the fixed fallback values and semantic markers that the bridge
 * supplies itself. Changing a value here changes the exported wire format.
 *
 * @see AttributeKeys
 * @see https://opentelemetry.io/
 */
interface AttributeValues
{
    /**
     * Fallback when a redacted occurrence or snapshot ID is not a string.
     */
    public const string REDACTED_ID = 'redacted';

    /**
     * Fallback when a redacted occurrence or snapshot type is not a string.
     * Also used as the redacted log/span event body.
     */
    public const string REDACTED_TYPE = 'runit.artifact.redacted';

    /**
     * Fallback when a redacted occurrence source is not a string.
     */
    public const string REDACTED_SOURCE = 'runit://redacted';

    /**
     * Fallback when a redacted snapshot classification is not a string.
     */
    public const string UNKNOWN_CLASSIFICATION = 'unknown';

    /**
     * Marker value for {@see AttributeKeys::REDACTED}.
     */
    public const bool REDACTED_MARKER = true;

    /**
     * Empty fallback for missing identity attributes (execution ID, activity ID,
     * snapshot ID, occurrence ID) in redacted projections.
     */
    public const string EMPTY_IDENTITY = '';

    /**
     * Empty fallback for missing kind and status attributes in redacted spans.
     */
    public const string EMPTY_KIND_STATUS = '';

    /**
     * Empty fallback for missing exception class/message/trace details in
     * redacted span events.
     */
    public const string EMPTY_EXCEPTION_DETAIL = '';

    /**
     * Default span name (and EXECUTION_NAME attribute value) when an execution
     * name is missing or empty.
     */
    public const string DEFAULT_EXECUTION_NAME = 'execution';

    /**
     * Default span name (and ACTIVITY_NAME attribute value) when an activity
     * name is missing or empty.
     */
    public const string DEFAULT_ACTIVITY_NAME = 'activity';
}
