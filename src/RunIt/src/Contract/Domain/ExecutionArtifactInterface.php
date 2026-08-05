<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for every immutable semantic artifact produced during an execution.
 *
 * Executions, activities, measurements, observations, occurrences, and
 * snapshots all implement this marker contract. A reader may rely on any
 * artifact being {@link NormalizableInterface} without depending on a
 * framework or adapter.
 *
 * Concrete artifact types combine this contract with the ownership, timing,
 * sequencing, and attribute contracts they need (for example,
 * `MeasurementInterface extends ExecutionArtifactInterface, ExecutionOwnedInterface,
 * TimestampedInterface, SequencedInterface, AttributedInterface`).
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionArtifactInterface extends NormalizableInterface
{
}
