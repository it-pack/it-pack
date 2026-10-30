<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Artifact;

use ItPack\AsyncIt\Contract\ValueObjectInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Minimal scheduling failure artifact published when a task throws.
 *
 * It carries only the failure message so the recursion-guarded publication path
 * has something deterministic to emit. The reference publisher is a no-op, but
 * the artifact shape is ready for a real adapter in Phase 3.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class SchedulerFailureArtifact implements ExecutionArtifactInterface, ValueObjectInterface
{
    public function __construct(public readonly string $message)
    {
    }

    public function normalize(): array
    {
        return [
            'kind' => ArtifactVocabulary::KIND_SCHEDULER_FAILURE,
            'message' => $this->message,
        ];
    }
}
