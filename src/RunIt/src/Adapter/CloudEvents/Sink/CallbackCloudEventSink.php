<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Sink;

use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSinkInterface;

/**
 * Sink that forwards every CloudEvent to a caller-supplied callback.
 *
 * Useful for in-memory capture in tests, local buffers, and custom transport
 * wiring. The callback receives the immutable event and must not mutate it.
 *
 * @see https://w3id.org/itpack/runit#CloudEvent
 */
final readonly class CallbackCloudEventSink implements CloudEventSinkInterface
{
    /**
     * @param callable(CloudEvent): void $callback
     */
    public function __construct(
        private mixed $callback,
    ) {
    }

    public function send(CloudEvent $event): void
    {
        ($this->callback)($event);
    }
}
