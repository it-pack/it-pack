<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\CloudEvents;

use Throwable;

/**
 * Contract for delivering a CloudEvent.
 *
 * @see https://cloudevents.io/
 */
interface CloudEventSinkInterface
{
    /**
     * @throws Throwable implementations may throw; callers decide the failure policy
     */
    public function send(CloudEventInterface $event): void;
}
