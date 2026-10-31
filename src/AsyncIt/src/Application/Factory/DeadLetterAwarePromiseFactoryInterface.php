<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Application\Federation\DeadLetterAwarePromise;
use ItPack\AsyncIt\Contract\Port\DeadLetterQueueInterface;
use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\AsyncIt\Contract\RemoteTaskInterface;

/**
 * Factory for {@see DeadLetterAwarePromise}.
 *
 * Centralises construction so the `ForbidNewOutsideFactoryBuilderRule`
 * is respected: `FederatedScheduler` (in `Application\Federation`) may not
 * `new` a type outside `*\Factory\*` / `*\Builder\*` namespaces.
 *
 * @see https://w3id.org/itpack/asyncit#DeadLetterQueue
 * @see https://w3id.org/itpack/asyncit#Promise
 */
interface DeadLetterAwarePromiseFactoryInterface
{
    /**
     * Wraps a remote promise so that a local timeout triggers dead-lettering.
     *
     * @param PromiseInterface $inner The underlying remote result promise.
     * @param RemoteTaskInterface $task The task definition to dead-letter on timeout.
     * @param DeadLetterQueueInterface $deadLetterQueue Queue for timed-out tasks.
     * @param string $timeoutReason Reason recorded when dead-lettering.
     * @return PromiseInterface A promise that dead-letters $task on local timeout.
     */
    public function create(
        PromiseInterface $inner,
        RemoteTaskInterface $task,
        DeadLetterQueueInterface $deadLetterQueue,
        string $timeoutReason = 'local timeout',
    ): PromiseInterface;
}
