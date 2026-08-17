<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an immutable occurrence artifact.
 *
 * An occurrence is an immutable fact that happened during execution, expressed
 * through a stable, versioned occurrence type (for example a CloudEvents `type`),
 * an explicit source, and an optional subject. Occurrences carry an optional
 * recursively-primitive data payload, a content type, and bounded extension
 * attributes. The type is always explicit; a PHP class name is never used as an
 * implicit event type.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
interface OccurrenceInterface extends ExecutionArtifactInterface, ExecutionOwnedInterface, ActivityOwnedInterface, TimestampedInterface, SequencedInterface
{
    public function id(): OccurrenceIdInterface;

    public function type(): OccurrenceTypeInterface;

    public function source(): string;

    public function subject(): ?string;

    public function time(): InstantInterface;

    /**
     * @return mixed an arbitrary, recursively-primitive payload (scalar, null, or
     *               scalar-only list/map), validated on construction
     */
    public function data(): mixed;

    public function dataSchema(): ?string;

    public function contentType(): string;

    /**
     * Returns the bounded extension attributes.
     *
     * @return array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>>
     */
    public function extensionAttributes(): array;
}
