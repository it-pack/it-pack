<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Federation;

use ItPack\AsyncIt\Contract\Port\DeadLetterQueueInterface;
use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\AsyncIt\Contract\RemoteTaskInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * Promise wrapper that dead-letters the wrapped task when a local timeout
 * elapses before the remote task completes.
 *
 * Delegates all calls to the inner promise. When {@see self::wait()} returns
 * false because the timeout elapsed, the wrapped task is enqueued to the
 * dead-letter queue exactly once.
 *
 * @see https://w3id.org/itpack/asyncit#Promise
 * @see https://w3id.org/itpack/asyncit#DeadLetterQueue
 */
final class DeadLetterAwarePromise implements PromiseInterface
{
    private bool $enqueued = false;

    public function __construct(
        private readonly PromiseInterface $inner,
        private readonly RemoteTaskInterface $task,
        private readonly DeadLetterQueueInterface $deadLetterQueue,
        private readonly string $timeoutReason = 'local timeout',
    ) {
    }

    public function isDone(): bool
    {
        return $this->inner->isDone();
    }

    public function wait(?DurationInterface $timeout = null): bool
    {
        $result = $this->inner->wait($timeout);

        if (!$result && !$this->enqueued) {
            $this->enqueued = true;
            $this->deadLetterQueue->enqueue($this->task, $this->timeoutReason);
        }

        return $result;
    }

    public function get(): mixed
    {
        return $this->inner->get();
    }
}
