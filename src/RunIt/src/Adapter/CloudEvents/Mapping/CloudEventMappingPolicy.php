<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Mapping;

use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;

/**
 * Configuration for CloudEvent mapping and serialization.
 *
 * The policy carries the bounds and defaults that govern how RunIt artifacts
 * become CloudEvents: the spec version used when none is supplied and the
 * maximum size of a serialized event. Keeping these in a dedicated value object
 * separates the *what* of mapping (the mappers) from the *how big / which version*
 * knobs an application may tune.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#CloudEvent
 */
final readonly class CloudEventMappingPolicy
{
    /**
     * Default ceiling for a serialized CloudEvent, in bytes. The bound keeps a
     * single event from becoming an unbounded payload on the wire or in a buffer.
     */
    public const int DEFAULT_MAX_SERIALIZED_BYTES = 65_536;

    public function __construct(
        public string $specVersion = CloudEvent::SPEC_VERSION_1_0,
        public int $maxSerializedBytes = self::DEFAULT_MAX_SERIALIZED_BYTES,
    ) {
    }

    public static function default(): self
    {
        return new self();
    }
}
