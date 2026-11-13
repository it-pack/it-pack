<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

interface TypedExecutionArtifactInterface extends ExecutionArtifactInterface
{
    /**
     * Kind this artifact represents.
     */
    public function artifactType(): string;
}
