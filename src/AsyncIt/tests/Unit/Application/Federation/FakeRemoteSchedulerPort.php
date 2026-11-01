<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Contract\Port\RemoteSchedulerPortInterface;
use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\AsyncIt\Contract\RemoteTaskInterface;

final class FakeRemoteSchedulerPort implements RemoteSchedulerPortInterface
{
    public ?RemoteTaskInterface $lastTask = null;

    public function execute(RemoteTaskInterface $task): PromiseInterface
    {
        $this->lastTask = $task;

        return new ResolvedPromise('remote-result');
    }
}
