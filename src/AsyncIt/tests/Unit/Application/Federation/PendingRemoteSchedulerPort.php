<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Contract\Port\RemoteSchedulerPortInterface;
use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\AsyncIt\Contract\RemoteTaskInterface;

final class PendingRemoteSchedulerPort implements RemoteSchedulerPortInterface
{
    public function execute(RemoteTaskInterface $task): PromiseInterface
    {
        return new PendingPromise();
    }
}
