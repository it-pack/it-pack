<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\CloudEvents;

/**
 * Contract for an immutable CloudEvent value object.
 *
 * @see https://cloudevents.io/
 */
interface CloudEventInterface
{
    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array;

    /**
     * @param array<array-key, mixed> $extensions
     */
    public function withExtensions(array $extensions): self;
}
