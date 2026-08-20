<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Telemetry;

/**
 * Stable OpenTelemetry attribute keys used by the RunIt bridge.
 *
 * All keys are namespaced under `runit.` so they never collide with
 * application or vendor attributes. Attributes that could carry high-cardinality
 * values (execution id, activity id, correlation id) are recorded for
 * correlation but are deliberately excluded from metric dimensions by default.
 *
 * @see https://w3id.org/itpack/runit#OpenTelemetry
 */
final class AttributeKeys
{
    public const string EXECUTION_ID = 'runit.execution.id';

    public const string EXECUTION_NAME = 'runit.execution.name';

    public const string EXECUTION_KIND = 'runit.execution.kind';

    public const string EXECUTION_PARENT_ID = 'runit.execution.parent_id';

    public const string EXECUTION_STATUS = 'runit.execution.status';

    public const string ACTIVITY_ID = 'runit.activity.id';

    public const string ACTIVITY_NAME = 'runit.activity.name';

    public const string ACTIVITY_KIND = 'runit.activity.kind';

    public const string ACTIVITY_STATUS = 'runit.activity.status';

    public const string ACTIVITY_PARENT_ID = 'runit.activity.parent_id';

    public const string OBSERVATION_ID = 'runit.observation.id';

    public const string DIAGNOSTIC_CODE = 'runit.diagnostic.code';

    public const string OCCURRENCE_ID = 'runit.occurrence.id';

    public const string OCCURRENCE_TYPE = 'runit.occurrence.type';

    public const string OCCURRENCE_SOURCE = 'runit.occurrence.source';

    public const string OCCURRENCE_SUBJECT = 'runit.occurrence.subject';

    public const string SNAPSHOT_ID = 'runit.snapshot.id';

    public const string SNAPSHOT_TYPE = 'runit.snapshot.type';

    public const string SNAPSHOT_CLASSIFICATION = 'runit.snapshot.classification';

    public const string SNAPSHOT_HASH = 'runit.snapshot.hash';
}
