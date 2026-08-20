<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Contract;

use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;

/**
 * Delivery port for CloudEvents.
 *
 * A sink is the adapter-level boundary that physically emits a CloudEvent: to an
 * in-memory buffer, a callback, an HTTP endpoint, or any other transport. Sinks
 * must not change the event and must not introduce hidden durable-delivery
 * guarantees; delivery semantics are documented by the concrete sink.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#CloudEvent
 */
interface CloudEventSinkInterface
{
    /**
     * Delivers a CloudEvent.
     *
     * @throws \Throwable implementations may throw; callers decide the failure policy
     */
    public function send(CloudEvent $event): void;
}
