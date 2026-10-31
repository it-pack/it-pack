<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\AsyncIt\Contract\RemoteTaskInterface;

/**
 * Application port for submitting a RemoteTask to a remote AsyncIt scheduler
 * instance.
 *
 * Implementations (e.g. gRPC bridge, AMQP bridge) translate the serializable
 * task definition into the remote runtime's wire protocol and return a
 * Promise whose result resolves when the remote task completes.
 *
 * Distributed cancellation degrades gracefully: a remote scheduler that
 * becomes unreachable is treated as a local timeout, and the task is routed
 * to a dead-letter queue rather than left hanging indefinitely.
 *
 * @see https://w3id.org/itpack/asyncit#RemoteSchedulerPort
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
interface RemoteSchedulerPortInterface
{
    /**
     * Submits a RemoteTask to a remote scheduler and returns a Promise whose
     * result resolves when the remote task completes.
     *
     * The returned Promise may never resolve if the remote scheduler is
     * unreachable; consumers should combine it with a local timeout via
     * {@see \ItPack\AsyncIt\Contract\PromiseInterface::wait()}.
     */
    public function execute(RemoteTaskInterface $task): PromiseInterface;
}
