<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Contract\Telemetry;

/**
 * Canonical activity and occurrence names emitted by the derivation pipeline
 * (REVIEW.md F-3 / R4-1). Activities are named stages of work; occurrences are
 * immutable facts. Names are low-cardinality dotted identifiers so sinks can
 * key on them without parsing.
 */
interface DeriveEvents
{
    /** Occurrence: a derivation run began (target attribute carries the FQCN). */
    public const string EVENT_DERIVATION_STARTED = 'derivation.started';

    /** Occurrence: a derivation run finished successfully. */
    public const string EVENT_DERIVATION_COMPLETED = 'derivation.completed';

    /** Activity: class/trait graph discovery. */
    public const string ACTIVITY_GRAPH_WALK = 'graph.walk';

    /** Activity: method-surface extraction and filtering for one adaptee. */
    public const string ACTIVITY_SURFACE_EXTRACT = 'surface.extract';

    /** Activity: leakage-invariant verification for one assembled interface. */
    public const string ACTIVITY_LEAKAGE_AUDIT = 'leakage.audit';

    /** Activity: drift comparison inside {@see \ItPack\DeriveIt\DeriveIt::diff()}. */
    public const string ACTIVITY_DIFF_COMPUTE = 'diff.compute';

    /** Occurrence: the committed source diverges from a fresh derivation. */
    public const string OCCURRENCE_DRIFT_DETECTED = 'drift.detected';

    /** Occurrence: the committed source matches a fresh derivation. */
    public const string OCCURRENCE_DRIFT_CLEAN = 'drift.clean';
}
