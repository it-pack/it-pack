<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use Throwable;

final class FakeHealth implements SchedulerHealthInterface
{
    public function isHealthy(): bool
    {
        return true;
    }

    public function pendingTaskCount(): int
    {
        return 0;
    }

    public function activeHandleCount(): int
    {
        return 0;
    }

    public function uptime(): DurationInterface
    {
        return new FakeDuration(0);
    }

    /** @return list<Throwable> */
    public function recentErrors(int $n): array
    {
        return [];
    }
}
