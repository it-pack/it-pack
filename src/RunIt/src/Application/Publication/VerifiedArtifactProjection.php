<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/** @internal Immutable identity-verified projection awaiting classification. */
final readonly class VerifiedArtifactProjection implements ExecutionArtifactInterface
{
    /** @param array<string, mixed> $normalized */
    public function __construct(private array $normalized, private string $artifactType)
    {
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
