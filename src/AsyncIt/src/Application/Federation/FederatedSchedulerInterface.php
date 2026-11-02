<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Federation;

use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\AsyncIt\Contract\RemoteTaskInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * Schedules RemoteTask submissions across a federation of remote AsyncIt
 * schedulers.
 *
 * Backs {@see FederatedScheduler} so federation remains mockable and
 * interface-driven.
 *
 * @see https://w3id.org/itpack/asyncit#FederatedScheduler
 * @see https://w3id.org/itpack/asyncit#RemoteSchedulerPort
 * @api
 */
interface FederatedSchedulerInterface
{
    /**
     * Submits a RemoteTask to a remote scheduler via the port.
     *
     * @param DurationInterface|null $localTimeout Optional monotonic deadline
     *        after which the task is dead-lettered locally.
     * @return PromiseInterface Resolves when the remote task completes.
     */
    public function submit(RemoteTaskInterface $task, ?DurationInterface $localTimeout = null): PromiseInterface;
}
