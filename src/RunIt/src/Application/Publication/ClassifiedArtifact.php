<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\TrustedArtifactProjectionInterface;

/**
 * A classified, bounded projection of an artifact for safe publication.
 *
 * Unlike the deny-list redactor, the classified projection uses an explicit
 * allowlist by artifact kind: only fields known for that kind are preserved,
 * and all other fields are dropped. The projection is immutable and bounded:
 * depth 16, 1,024 nodes, 4,096 bytes per string, 1,048,576 bytes total, and
 * field counts capped by SecurityLimits. Objects, resources, and unknown fields
 * are rejected; on exhaustion optional fields are omitted or replaced, required
 * fields cause the artifact to be dropped with a payload-free report.
 *
 * The artifactType() is a trusted delivery signal set by the classifying
 * publisher, not inferred from user-controlled normalized fields.
 *
 * @internal classified projection; not a public contract.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ClassifiedArtifact implements ExecutionArtifactInterface, TrustedArtifactProjectionInterface
{
    /**
     * @param array<string, mixed> $normalized the classified, bounded normalization
     * @param string               $artifactType trusted artifact kind set by the classifier
     */
    public function __construct(
        private array $normalized,
        private string $artifactType,
    ) {
    }

    public function normalize(): array
    {
        return $this->normalized;
    }

    public function artifactType(): string
    {
        return $this->artifactType;
    }
}
