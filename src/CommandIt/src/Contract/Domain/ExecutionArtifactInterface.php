<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Domain;

/**
 * Marker contract for every immutable semantic artifact produced during a
 * command execution.
 *
 * This mirrors RunIt's `ExecutionArtifactInterface` so a future RunIt adapter
 * can align CommandIt executions with the RunIt pipeline without coupling the
 * two codebases. It is intentionally minimal here; it can later extend a
 * normalization contract when serialization lands (Milestone 7).
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionArtifactInterface
{
}
