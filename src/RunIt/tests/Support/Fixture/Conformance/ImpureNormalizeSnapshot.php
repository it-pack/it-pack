<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Conformance;

use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\SnapshotTypeInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;

/**
 * Snapshot that delegates everything except {@see normalize()}, which changes
 * between calls, so mutation-detection branches of the verifiers can fire.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class ImpureNormalizeSnapshot implements SnapshotInterface
{
    private int $invocations = 0;

    public function __construct(private readonly SnapshotInterface $inner)
    {
    }

    public function normalize(): array
    {
        return ['normalize.invocations' => ++$this->invocations];
    }

    public function id(): SnapshotIdInterface
    {
        return $this->inner->id();
    }

    public function type(): SnapshotTypeInterface
    {
        return $this->inner->type();
    }

    public function classification(): SnapshotClassificationInterface
    {
        return $this->inner->classification();
    }

    public function schemaVersion(): int
    {
        return $this->inner->schemaVersion();
    }

    public function payload(): mixed
    {
        return $this->inner->payload();
    }

    public function contentType(): string
    {
        return $this->inner->contentType();
    }

    public function contentHash(): string
    {
        return $this->inner->contentHash();
    }

    public function predecessorId(): ?SnapshotIdInterface
    {
        return $this->inner->predecessorId();
    }

    public function executionId(): ExecutionIdInterface
    {
        return $this->inner->executionId();
    }

    public function activityId(): ?StringIdentifierInterface
    {
        return $this->inner->activityId();
    }

    public function timestamp(): int
    {
        return $this->inner->timestamp();
    }

    public function attributes(): array
    {
        return $this->inner->attributes();
    }

    public function count(): int
    {
        return $this->inner->count();
    }
}
