<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Application\Federation\DeadLetterAwarePromise;
use ItPack\AsyncIt\Contract\Port\DeadLetterQueueInterface;
use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\AsyncIt\Contract\RemoteTaskInterface;

/**
 * Builds {@see DeadLetterAwarePromise} instances.
 *
 * @see DeadLetterAwarePromiseFactoryInterface
 */
final class DeadLetterAwarePromiseFactory implements DeadLetterAwarePromiseFactoryInterface
{
    public function create(
        PromiseInterface $inner,
        RemoteTaskInterface $task,
        DeadLetterQueueInterface $deadLetterQueue,
        string $timeoutReason = 'local timeout',
    ): PromiseInterface {
        return new DeadLetterAwarePromise(
            $inner,
            $task,
            $deadLetterQueue,
            $timeoutReason,
        );
    }
}
