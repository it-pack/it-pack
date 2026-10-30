<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Artifact;

/**
 * Vocabulary constants for scheduler artifact kinds.
 *
 * These provide named, stable string identifiers for artifacts published by
 * the AsyncIt scheduler into the RunIt artifact stream.
 *
 * @see https://w3id.org/itpack/asyncit#Execution
 */
interface ArtifactVocabulary
{
    /**
     * Kind identifier for scheduling-failure artifacts published when a task throws.
     *
     * @see SchedulerFailureArtifact
     */
    public const KIND_SCHEDULER_FAILURE = 'asyncit.scheduler.failure';
}
