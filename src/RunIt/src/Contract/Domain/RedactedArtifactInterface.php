<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Marker for an artifact that was replaced by its redacted projection.
 *
 * Redaction is applied on the normalized form, so a redacted artifact can no
 * longer be downcast to its concrete domain type (for example an occurrence is
 * no longer an {@see OccurrenceInterface}). Adapters that dispatch on concrete
 * artifact types must recognize this marker and treat the artifact as an explicit
 * "suppressed" signal: they should still emit a visible, non-sensitive delivery
 * (carrying only safe identity) rather than silently dropping the artifact.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface RedactedArtifactInterface extends TypedExecutionArtifactInterface
{
    /**
     * Artifact kind this redacted projection represents.
     *
     * Set by the redactor that produced the projection. Adapters must route
     * redacted artifacts by this signal, never by inferring type from the
     * user-controlled normalized fields. This value alone does not establish
     * provenance; verify that separately at the publication boundary.
     */
    public function artifactType(): string;
}
