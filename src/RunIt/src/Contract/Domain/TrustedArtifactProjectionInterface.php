<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Marker for an artifact projection produced by the SDK's own classifying
 * publisher, as opposed to a user-supplied {@see RedactedArtifactInterface}.
 *
 * The artifactType() on a trusted projection is set internally by the SDK and
 * is not forgeable by callers. Sampling policies may rely on it to route
 * redacted observations by severity. User-supplied redacted artifacts must not
 * be trusted by their artifact type alone.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface TrustedArtifactProjectionInterface extends RedactedArtifactInterface
{
}
