<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Contract\Telemetry;

/**
 * Canonical measurement names and measurement-attribute values emitted by the
 * derivation pipeline (REVIEW.md F-3 / R4-1).
 */
interface DeriveMeasurements
{
    /** Count of classes or traits discovered by a graph walk. */
    public const string MEASUREMENT_FAMILY_MEMBERS = 'family.members';

    /** Count of methods kept on the derived surface. */
    public const string MEASUREMENT_METHODS_DERIVED = 'methods.derived';

    /** Count of methods dropped by the filter pipeline. */
    public const string MEASUREMENT_METHODS_DROPPED = 'methods.dropped';

    /** Size of the effective substitution map applied to a surface. */
    public const string MEASUREMENT_SUBSTITUTIONS_APPLIED = 'substitutions.applied';

    /** Count of constants lifted into the derived interface. */
    public const string MEASUREMENT_CONSTANTS_LIFTED = 'constants.lifted';

    /** Rendered source length in bytes. */
    public const string MEASUREMENT_SOURCE_BYTES = 'source.bytes';

    /** `surface` attribute value: the composed trait-interface surfaces. */
    public const string SURFACE_TRAIT_INTERFACES = 'trait-interfaces';

    /** `surface` attribute value: the adaptee's own remaining surface. */
    public const string SURFACE_MAIN = 'main';

    /** `graph` attribute value: discovery over classes (family mode). */
    public const string GRAPH_CLASSES = 'classes';

    /** `graph` attribute value: discovery over traits (per-trait mode). */
    public const string GRAPH_TRAITS = 'traits';
}
