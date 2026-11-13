<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

use ItPack\RunIt\Contract\Domain\ArtifactType;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;

interface RedactedArtifactFactoryInterface
{
    /**
     * @param array<string, mixed> $normalized
     */
    public function create(array $normalized, ArtifactType|string|null $artifactType = null): RedactedArtifactInterface;

    public function artifactType(): string;

    public function withArtifactType(
        ArtifactType|string $artifactType = ArtifactType::UNKNOWN
    ): RedactedArtifactFactoryInterface;
}
