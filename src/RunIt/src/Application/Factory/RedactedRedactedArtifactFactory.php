<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Factory;

use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Contract\Application\Publication\RedactedArtifactFactoryInterface;
use ItPack\RunIt\Contract\Domain\ArtifactType;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;

/** @param array<string, mixed> $normalized */
final readonly class RedactedRedactedArtifactFactory implements RedactedArtifactFactoryInterface
{
    private string $artifactType;

    public function __construct(ArtifactType|string $artifactType = ArtifactType::UNKNOWN)
    {
        $this->artifactType = $artifactType instanceof ArtifactType
            ? $artifactType->value
            : $artifactType;
    }

    public function create(
        array $normalized,
        ArtifactType|string|null $artifactType = null
    ): RedactedArtifactInterface {
        return new RedactedArtifact(
            $normalized,
            $artifactType ?? $this->artifactType
        );
    }

    public function artifactType(): string
    {
        return $this->artifactType;
    }

    public function withArtifactType(
        ArtifactType|string $artifactType = ArtifactType::UNKNOWN
    ): RedactedArtifactFactoryInterface {
        return new self($artifactType);
    }
}
