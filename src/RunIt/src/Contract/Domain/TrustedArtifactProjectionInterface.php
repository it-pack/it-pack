<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Marker used for classified artifact projections.
 *
 * This public interface can be implemented by callers. Neither implementing it
 * nor supplying artifactType() proves provenance. Consumers may route by that
 * type, but must establish trust through an upstream provenance check.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface TrustedArtifactProjectionInterface extends RedactedArtifactInterface
{
}
