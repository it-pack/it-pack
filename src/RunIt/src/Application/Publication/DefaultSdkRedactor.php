<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/** @internal Marks the SDK-installed default, not an explicitly configured redactor. */
final readonly class DefaultSdkRedactor implements RedactorInterface
{
    public function __construct(private DefaultArtifactRedactor $delegate = new DefaultArtifactRedactor())
    {
    }

    public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
    {
        return $this->delegate->redact($artifact);
    }
}
