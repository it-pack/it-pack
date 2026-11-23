<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Test double that wraps an execution artifact and tags its normalized name as redacted.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class RedactedTagArtifact implements ExecutionArtifactInterface
{
    public function __construct(private ExecutionArtifactInterface $inner)
    {
    }

    public function normalize(): array
    {
        $normalized = $this->inner->normalize();
        if (isset($normalized['name'])) {
            $normalized['name'] = 'redacted-name';
        }

        return $normalized;
    }
}
